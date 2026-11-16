<?php

namespace App\Service;

/**
 * Fiches livres et DVD/Blu-ray depuis E.Leclerc et la Fnac.
 * Livre : ISBN ou URL. DVD : code EAN.
 */
class ShopCatalogService
{
    public function __construct(private RemotePageClient $pages)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findBooksByIsbn(string $isbn): array
    {
        $isbn = $this->digits($isbn);
        if ($isbn === '') {
            return [];
        }

        $books = [];
        $leclerc = $this->leclercBookByCode($isbn);
        if ($leclerc !== null) {
            $books[] = $leclerc;
        }
        $fnac = $this->fnacBookByCode($isbn);
        if ($fnac !== null) {
            $books[] = $fnac;
        }

        return $books;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBookByUrl(string $url): ?array
    {
        $url = trim($url);
        if (str_contains($url, 'e.leclerc')) {
            return $this->bookFromLeclercHtml($this->pages->get($url), $url);
        }
        if (str_contains($url, 'fnac.com')) {
            return $this->bookFromFnacHtml($this->pages->get($url), $url);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findDvdsByEan(string $ean): array
    {
        $ean = $this->digits($ean);
        if ($ean === '') {
            return [];
        }

        $dvds = [];
        $leclerc = $this->leclercDvdByCode($ean);
        if ($leclerc !== null) {
            $dvds[] = $leclerc;
        }
        $fnac = $this->fnacDvdByCode($ean);
        if ($fnac !== null) {
            $dvds[] = $fnac;
        }

        return $dvds;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDvdDetails(string $reference): ?array
    {
        if (str_starts_with($reference, 'leclerc:')) {
            $url = $this->leclercAbsolute(substr($reference, strlen('leclerc:')));
            $html = $this->pages->get($url);

            return $html === null ? null : $this->dvdFromLeclercHtml($html, $url);
        }
        if (str_starts_with($reference, 'fnac:')) {
            $url = substr($reference, strlen('fnac:'));
            $html = $this->pages->get($url);

            return $html === null ? null : $this->dvdFromFnacHtml($html, $url);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function leclercBookByCode(string $code): ?array
    {
        $productUrl = $this->firstLeclercProduct('https://www.e.leclerc/recherche?q=' . rawurlencode($code), $code);
        if ($productUrl === null) {
            return null;
        }
        $html = $this->pages->get($productUrl);

        return $this->bookFromLeclercHtml($html, $productUrl);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function leclercDvdByCode(string $code): ?array
    {
        $productUrl = $this->firstLeclercProduct('https://www.e.leclerc/recherche?q=' . rawurlencode($code), $code);
        if ($productUrl === null) {
            return null;
        }
        $html = $this->pages->get($productUrl);

        return $html === null ? null : $this->dvdFromLeclercHtml($html, $productUrl);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fnacBookByCode(string $code): ?array
    {
        $productUrl = $this->firstFnacProduct($code);
        if ($productUrl === null) {
            return null;
        }
        $html = $this->pages->get($productUrl);

        return $this->bookFromFnacHtml($html, $productUrl);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fnacDvdByCode(string $code): ?array
    {
        $productUrl = $this->firstFnacProduct($code);
        if ($productUrl === null) {
            return null;
        }
        $html = $this->pages->get($productUrl);

        return $html === null ? null : $this->dvdFromFnacHtml($html, $productUrl);
    }

    private function firstLeclercProduct(string $searchUrl, string $code): ?string
    {
        $html = $this->pages->get($searchUrl);
        if ($html === null || !str_contains($html, $code)) {
            return null;
        }
        if (!preg_match_all('~href="(/fp/[^"#?]+)"~', $html, $matches)) {
            return null;
        }
        foreach (array_unique($matches[1]) as $href) {
            if (str_contains($href, $code)) {
                return $this->leclercAbsolute($href);
            }
        }

        return $this->leclercAbsolute($matches[1][0]);
    }

    private function firstFnacProduct(string $code): ?string
    {
        $html = $this->pages->get('https://www.fnac.com/SearchResult/ResultList.aspx?SCat=0&Search=' . rawurlencode($code) . '&sft=1&sa=0');
        if ($html === null || !str_contains($html, $code)) {
            return null;
        }
        if (!preg_match_all('~https://www\.fnac\.com/a\d+/[^"#?\s]+~', $html, $matches)) {
            return null;
        }
        $links = array_values(array_unique($matches[0]));

        return $links[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bookFromLeclercHtml(?string $html, string $url): ?array
    {
        if ($html === null) {
            return null;
        }
        $product = $this->leclercProduct($html);
        $fields = $this->leclercFields($html);
        if ($this->isVideo($fields)) {
            return null;
        }
        $titre = $product['name'] ?? '';
        if ($titre === '') {
            return null;
        }

        return $this->bookRecord(
            $titre,
            $this->splitNames($fields['Auteur(s)'] ?? $fields['Auteur'] ?? ''),
            $fields['Editeur'] ?? '',
            $product['gtin13'] ?? $product['sku'] ?? '',
            $this->yearOf($fields['Date de parution'] ?? ''),
            $this->intOf($fields['Nombre de pages'] ?? ''),
            $product['description'] ?? '',
            $product['image'] ?? '',
            $product['price'] ?? null,
            'E.Leclerc',
            $url,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bookFromFnacHtml(?string $html, string $url): ?array
    {
        if ($html === null) {
            return null;
        }
        $fields = $this->fnacFields($html);
        if ($this->isVideo($fields)) {
            return null;
        }
        $titre = $this->fnacTitle($html);
        if ($titre === '') {
            return null;
        }

        return $this->bookRecord(
            $titre,
            $this->fnacAuthors($html, $fields),
            $fields['Editeur'] ?? '',
            $fields['EAN'] ?? $fields['ISBN'] ?? '',
            $this->yearOf($fields['Date de parution'] ?? $this->fnacSubtitle($html, 'Paru le')),
            $this->intOf($fields['Nombre de pages'] ?? ''),
            $this->fnacDescription($html),
            $this->fnacImage($html),
            $this->fnacPrice($html),
            'Fnac',
            $url,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dvdFromLeclercHtml(string $html, string $url): ?array
    {
        $product = $this->leclercProduct($html);
        $fields = $this->leclercFields($html);
        if (!$this->isVideo($fields) || ($product['name'] ?? '') === '') {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return $this->dvdRecord(
            'leclerc:' . $path,
            $product['name'],
            $fields['Support'] ?? '',
            $this->yearOf($fields['Date de sortie'] ?? ''),
            $fields['Editeur'] ?? '',
            $product['image'] ?? '',
            $fields['Type de vidéo'] ?? $product['name'],
            $fields['Edition'] ?? '',
            $product['description'] ?? '',
            $product['gtin13'] ?? $product['sku'] ?? '',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dvdFromFnacHtml(string $html, string $url): ?array
    {
        $fields = $this->fnacFields($html);
        $titre = $this->fnacTitle($html);
        if (!$this->isVideo($fields) || $titre === '') {
            return null;
        }

        return $this->dvdRecord(
            'fnac:' . strtok($url, '?'),
            $titre,
            $fields['Support'] ?? $fields['Format'] ?? '',
            $this->yearOf($fields['Date de sortie'] ?? $fields['Date de parution'] ?? ''),
            $fields['Editeur'] ?? $fields['Éditeur'] ?? '',
            $this->fnacImage($html),
            $fields['Type de produit'] ?? $fields['Genre'] ?? $titre,
            $fields['Edition'] ?? $fields['Édition'] ?? '',
            $this->fnacDescription($html),
            $fields['EAN'] ?? '',
        );
    }

    /**
     * @param list<string> $auteurs
     * @return array<string, mixed>
     */
    private function bookRecord(
        string $titre,
        array $auteurs,
        string $editeur,
        string $isbn,
        ?int $annee,
        ?int $pages,
        string $resume,
        string $image,
        ?float $prix,
        string $source,
        string $sourceUrl,
    ): array {
        $tome = null;
        if (preg_match('/(?:tome|t)\s*(\d+)/iu', $titre, $match)) {
            $tome = (int) $match[1];
        }

        return [
            'titre' => $this->clean($titre),
            'tome' => $tome,
            'auteurs' => $auteurs,
            'editeur' => $this->clean($editeur),
            'isbn' => $this->digits($isbn),
            'annee' => $annee,
            'pages' => $pages,
            'resume' => $this->clean($resume),
            'image' => $image,
            'prix' => $prix,
            'source' => $source,
            'sourceUrl' => $sourceUrl,
            'categories' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dvdRecord(
        string $id,
        string $titre,
        string $support,
        ?int $annee,
        string $editeur,
        string $cover,
        string $typeHint,
        string $edition,
        string $description,
        string $ean,
    ): array {
        $titre = $this->clean($titre);
        $edition = $this->clean($edition);

        return [
            'id' => $id,
            'titre' => $titre,
            'format' => $this->discFormat($support . ' ' . $titre),
            'annee' => $annee,
            'editeur' => $this->clean($editeur),
            'cover' => $cover,
            'type' => preg_match('/saison|série|serie|intégrale|integrale/iu', $typeHint . ' ' . $titre) ? 'serie' : 'film',
            'edition' => $edition,
            'description' => $this->clean($description),
            'ean' => $this->digits($ean),
        ];
    }

    /**
     * @return array{name: string, description: string, gtin13: string, sku: string, image: string, price: ?float}
     */
    private function leclercProduct(string $html): array
    {
        $product = ['name' => '', 'description' => '', 'gtin13' => '', 'sku' => '', 'image' => '', 'price' => null];
        if (!preg_match_all('#<script type="application/ld\+json">(.*?)</script>#isu', $html, $matches)) {
            return $product;
        }
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            if (!is_array($data) || ($data['@type'] ?? '') !== 'Product') {
                continue;
            }
            $product['name'] = (string) ($data['name'] ?? '');
            $product['description'] = (string) ($data['description'] ?? '');
            $product['gtin13'] = (string) ($data['gtin13'] ?? '');
            $product['sku'] = (string) ($data['sku'] ?? '');
            $image = $data['image'] ?? '';
            $product['image'] = is_array($image) ? (string) ($image[0] ?? '') : (string) $image;
            $offer = $data['offers'][0]['price'] ?? null;
            $product['price'] = is_numeric($offer) ? (float) $offer : null;
            break;
        }

        return $product;
    }

    /**
     * @return array<string, string>
     */
    private function leclercFields(string $html): array
    {
        $fields = [];
        if (!preg_match_all(
            '#<!-- HTML_TAG_START -->([^<]+)<!-- HTML_TAG_END -->\s*</th>\s*<td[^>]*>\s*<!-- HTML_TAG_START -->(.*?)<!-- HTML_TAG_END -->#isu',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            return $fields;
        }
        foreach ($matches as $match) {
            $label = $this->clean($match[1]);
            $value = $this->clean(strip_tags($match[2]));
            if ($label !== '' && $value !== '' && !isset($fields[$label])) {
                $fields[$label] = $value;
            }
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    private function fnacFields(string $html): array
    {
        $fields = [];
        if (!preg_match_all(
            '#<dt class="f-productProperties__term">([^<]+)</dt>\s*<dd[^>]*>(.*?)</dd>#isu',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            return $fields;
        }
        foreach ($matches as $match) {
            $label = $this->clean($match[1]);
            $value = $this->clean(strip_tags($match[2]));
            if ($label !== '' && $value !== '' && !isset($fields[$label])) {
                $fields[$label] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param array<string, string> $fields
     */
    private function isVideo(array $fields): bool
    {
        $support = strtolower(implode(' ', [
            $fields['Support'] ?? '',
            $fields['Format'] ?? '',
            $fields['Type de vidéo'] ?? '',
            $fields['Type de produit'] ?? '',
        ]));

        return preg_match('/blu-ray|dvd|4k|ultra hd|vidéo|video/', $support) === 1;
    }

    private function fnacTitle(string $html): string
    {
        if (preg_match('#<h1[^>]*class="[^"]*f-productHeader__heading[^"]*"[^>]*>(.*?)</h1>#isu', $html, $match)) {
            return $this->clean(strip_tags($match[1]));
        }

        return '';
    }

    /**
     * @param array<string, string> $fields
     * @return list<string>
     */
    private function fnacAuthors(string $html, array $fields): array
    {
        if (!empty($fields['Auteur'])) {
            return $this->splitNames($fields['Auteur']);
        }
        if (!preg_match_all('#f-productHeader-subTitleLink"[^>]*>(.*?)</a>#isu', $html, $matches)) {
            return [];
        }
        $names = [];
        foreach ($matches[1] as $raw) {
            $name = trim(preg_replace('/\([^)]*\)/', '', $this->clean(strip_tags($raw))) ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function fnacSubtitle(string $html, string $prefix): string
    {
        if (!preg_match('#f-productHeader-subTitle[^>]*>\s*' . preg_quote($prefix, '#') . '\s*([^<]+)#iu', $html, $match)) {
            return '';
        }

        return $this->clean($match[1]);
    }

    private function fnacDescription(string $html): string
    {
        if (preg_match('#class="f-productDesc__raw"[^>]*>(.*?)</div>#isu', $html, $match)) {
            return $this->clean(strip_tags($match[1]));
        }

        return '';
    }

    private function fnacImage(string $html): string
    {
        if (preg_match('#https://static\.fnac-static\.com/multimedia/[^"\s]+#', $html, $match)) {
            return $match[0];
        }

        return '';
    }

    private function fnacPrice(string $html): ?float
    {
        if (preg_match('#data-price="([0-9]+(?:\.[0-9]+)?)"#', $html, $match)) {
            return (float) $match[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function splitNames(string $value): array
    {
        $names = [];
        foreach (preg_split('/\s*,\s*/u', $value) ?: [] as $name) {
            $name = $this->clean($name);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function yearOf(string $value): ?int
    {
        if (preg_match('/(19|20)\d{2}/', $value, $match)) {
            return (int) $match[0];
        }

        return null;
    }

    private function intOf(string $value): ?int
    {
        if (preg_match('/\d+/', $value, $match)) {
            return (int) $match[0];
        }

        return null;
    }

    private function discFormat(string $label): string
    {
        $haystack = strtoupper($label);
        if (str_contains($haystack, '4K') || str_contains($haystack, 'ULTRA HD')) {
            return 'bluray4k';
        }
        if (str_contains($haystack, 'BLU')) {
            return 'bluray';
        }

        return 'dvd';
    }

    private function leclercAbsolute(string $href): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }

        return 'https://www.e.leclerc' . (str_starts_with($href, '/') ? $href : '/' . $href);
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode(str_replace("\xc2\xa0", ' ', $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}

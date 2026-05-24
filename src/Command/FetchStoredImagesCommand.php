<?php

namespace App\Command;

use App\Entity\BrickImage;
use App\Entity\Dvd;
use App\Entity\DvdUserCollection;
use App\Entity\Game;
use App\Entity\GameImage;
use App\Entity\KioskCollec;
use App\Entity\KioskNum;
use App\Entity\LienUserGame;
use App\Entity\Livre;
use App\Entity\Musique;
use App\Entity\MusiqueUserCollection;
use App\Entity\Trait\StoredImagePathTrait;
use App\Service\Media\ImageMediaType;
use App\Service\Media\ImageStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FetchStoredImagesCommand extends Command
{
    protected static $defaultName = 'app:media:fetch-stored-images';
    protected static $defaultDescription = 'Télécharge et enregistre localement les images (conserve les URL sources)';

    public function __construct(
        private EntityManagerInterface $em,
        private ImageStorageService $imageStorage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Type: all, books, magazines, dvd, musique, games, brick', 'all')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Réécrire même si stored_path existe')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Nombre max d\'enregistrements par type', null)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = strtolower((string) $input->getOption('type'));
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');
        $limit = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null;

        $types = $type === 'all'
            ? ['books', 'magazines', 'dvd', 'musique', 'games', 'brick']
            : [$type];

        $totalOk = 0;
        $totalSkip = 0;
        $totalFail = 0;

        foreach ($types as $t) {
            $io->section('Type : ' . $t);
            [$ok, $skip, $fail] = match ($t) {
                'books', 'livres', 'book' => $this->processBooks($io, $force, $dryRun, $limit),
                'magazines', 'magazine', 'kiosk' => $this->processMagazines($io, $force, $dryRun, $limit),
                'dvd' => $this->processDvds($io, $force, $dryRun, $limit),
                'musique', 'music' => $this->processMusique($io, $force, $dryRun, $limit),
                'games', 'game', 'jeux' => $this->processGames($io, $force, $dryRun, $limit),
                'brick', 'bricks', 'briques' => $this->processBricks($io, $force, $dryRun, $limit),
                default => $this->unknownType($io, $t),
            };
            $totalOk += $ok;
            $totalSkip += $skip;
            $totalFail += $fail;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            'Terminé — %d enregistré(s), %d ignoré(s), %d échec(s)%s',
            $totalOk,
            $totalSkip,
            $totalFail,
            $dryRun ? ' (dry-run)' : ''
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function unknownType(SymfonyStyle $io, string $t): array
    {
        $io->warning('Type inconnu : ' . $t);

        return [0, 0, 0];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function processBooks(SymfonyStyle $io, bool $force, bool $dryRun, ?int $limit): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('l')
            ->from(Livre::class, 'l')
            ->orderBy('l.id', 'ASC');

        if (!$force) {
            $qb->andWhere('l.storedPath IS NULL OR l.storedPath = :empty')
                ->setParameter('empty', '');
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $this->processEntities($io, $qb->getQuery()->getResult(), function (Livre $livre) use ($force, $dryRun) {
            $source = null;
            if ($livre->getImage2()) {
                $source = $this->imageStorage->resolveBookLegacyPath($livre->getImage2())
                    ?? (str_starts_with($livre->getImage2(), '/uploads/') ? $livre->getImage2() : null);
                if ($source === null && !str_starts_with($livre->getImage2(), 'http')) {
                    $source = '/uploads/covers/' . $livre->getImage2();
                } elseif ($livre->getImage2() && $this->imageStorage->isRemoteUrl($livre->getImage2())) {
                    $source = $livre->getImage2();
                }
            }

            if ($source === null && $livre->getImage()) {
                $blob = $this->readBlob($livre->getImage());
                if ($blob !== null && !$dryRun) {
                    $path = $this->imageStorage->storeBinary($blob, ImageMediaType::BOOK, 'livre-' . $livre->getId());
                    if ($path) {
                        $livre->setStoredPath($path);

                        return 'ok';
                    }
                }

                return $blob ? 'fail' : 'skip';
            }

            if ($source === null) {
                return 'skip';
            }

            if ($dryRun) {
                return 'ok';
            }

            $path = $this->imageStorage->mirrorToStorage(
                $source,
                ImageMediaType::BOOK,
                'livre-' . $livre->getId(),
                $livre->getStoredPath(),
                $force
            );

            if ($path) {
                $livre->setStoredPath($path);

                return 'ok';
            }

            return 'fail';
        });
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function processMagazines(SymfonyStyle $io, bool $force, bool $dryRun, ?int $limit): array
    {
        $ok = $skip = $fail = 0;

        $collecQb = $this->em->createQueryBuilder()->select('k')->from(KioskCollec::class, 'k')->orderBy('k.id', 'ASC');
        if (!$force) {
            $collecQb->andWhere('k.storedPath IS NULL OR k.storedPath = :e')->setParameter('e', '');
        }
        if ($limit) {
            $collecQb->setMaxResults($limit);
        }

        foreach ($collecQb->getQuery()->getResult() as $mag) {
            $result = $this->mirrorKioskBlob($mag, $mag->getImage(), ImageMediaType::MAGAZINE_COLLECTION, 'magazine-' . $mag->getId(), $force, $dryRun);
            $this->tally($result, $ok, $skip, $fail);
            $io->writeln(sprintf('  Magazine #%d : %s', $mag->getId(), $result));
        }

        $numQb = $this->em->createQueryBuilder()->select('n')->from(KioskNum::class, 'n')->orderBy('n.id', 'ASC');
        if (!$force) {
            $numQb->andWhere('n.storedPath IS NULL OR n.storedPath = :e')->setParameter('e', '');
        }
        if ($limit) {
            $numQb->setMaxResults($limit);
        }

        foreach ($numQb->getQuery()->getResult() as $numero) {
            $result = $this->mirrorKioskBlob($numero, $numero->getCouverture(), ImageMediaType::MAGAZINE_NUMERO, 'numero-' . $numero->getId(), $force, $dryRun);
            $this->tally($result, $ok, $skip, $fail);
            $io->writeln(sprintf('  Numéro #%d : %s', $numero->getId(), $result));
        }

        return [$ok, $skip, $fail];
    }

    /**
     * @param object $entity
     */
    private function mirrorKioskBlob(object $entity, mixed $blob, string $mediaType, string $basename, bool $force, bool $dryRun): string
    {
        /** @var StoredImagePathTrait $entity */
        if (!$force && $entity->getStoredPath() && $this->imageStorage->fileExistsForWebPath($entity->getStoredPath())) {
            return 'skip';
        }

        $binary = $this->readBlob($blob);
        if ($binary === null) {
            return 'skip';
        }

        if ($dryRun) {
            return 'ok';
        }

        $path = $this->imageStorage->storeBinary($binary, $mediaType, $basename);
        if ($path) {
            $entity->setStoredPath($path);

            return 'ok';
        }

        return 'fail';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function processDvds(SymfonyStyle $io, bool $force, bool $dryRun, ?int $limit): array
    {
        $qb = $this->em->createQueryBuilder()->select('d')->from(Dvd::class, 'd')->orderBy('d.id', 'ASC');
        if (!$force) {
            $qb->andWhere('d.storedPath IS NULL OR d.storedPath = :e')->setParameter('e', '');
        }
        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $counts = $this->processEntities($io, $qb->getQuery()->getResult(), fn (Dvd $d) => $this->mirrorCoverEntity(
            $d->getCoverUrl(),
            $d->getStoredPath(),
            ImageMediaType::DVD,
            'dvd-' . $d->getId(),
            fn (?string $p) => $d->setStoredPath($p),
            $force,
            $dryRun
        ));

        $qb2 = $this->em->createQueryBuilder()->select('u')->from(DvdUserCollection::class, 'u')->orderBy('u.id', 'ASC');
        if (!$force) {
            $qb2->andWhere('u.storedPath IS NULL OR u.storedPath = :e')->setParameter('e', '');
        }
        if ($limit) {
            $qb2->setMaxResults($limit);
        }

        $c2 = $this->processEntities($io, $qb2->getQuery()->getResult(), fn (DvdUserCollection $u) => $this->mirrorCoverEntity(
            $u->getImagePerso(),
            $u->getStoredPath(),
            ImageMediaType::DVD_USER,
            'dvd-user-' . $u->getId(),
            fn (?string $p) => $u->setStoredPath($p),
            $force,
            $dryRun
        ));

        return [$counts[0] + $c2[0], $counts[1] + $c2[1], $counts[2] + $c2[2]];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function processMusique(SymfonyStyle $io, bool $force, bool $dryRun, ?int $limit): array
    {
        $qb = $this->em->createQueryBuilder()->select('m')->from(Musique::class, 'm')->orderBy('m.id', 'ASC');
        if (!$force) {
            $qb->andWhere('m.storedPath IS NULL OR m.storedPath = :e')->setParameter('e', '');
        }
        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $counts = $this->processEntities($io, $qb->getQuery()->getResult(), fn (Musique $m) => $this->mirrorCoverEntity(
            $m->getCoverUrl(),
            $m->getStoredPath(),
            ImageMediaType::MUSIQUE,
            'musique-' . $m->getId(),
            fn (?string $p) => $m->setStoredPath($p),
            $force,
            $dryRun
        ));

        $qb2 = $this->em->createQueryBuilder()->select('u')->from(MusiqueUserCollection::class, 'u')->orderBy('u.id', 'ASC');
        if (!$force) {
            $qb2->andWhere('u.storedPath IS NULL OR u.storedPath = :e')->setParameter('e', '');
        }
        if ($limit) {
            $qb2->setMaxResults($limit);
        }

        $c2 = $this->processEntities($io, $qb2->getQuery()->getResult(), fn (MusiqueUserCollection $u) => $this->mirrorCoverEntity(
            $u->getImagePerso(),
            $u->getStoredPath(),
            ImageMediaType::MUSIQUE_USER,
            'musique-user-' . $u->getId(),
            fn (?string $p) => $u->setStoredPath($p),
            $force,
            $dryRun
        ));

        return [$counts[0] + $c2[0], $counts[1] + $c2[1], $counts[2] + $c2[2]];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function processGames(SymfonyStyle $io, bool $force, bool $dryRun, ?int $limit): array
    {
        $qb = $this->em->createQueryBuilder()->select('g')->from(Game::class, 'g')->orderBy('g.id', 'ASC');
        if (!$force) {
            $qb->andWhere('g.storedPath IS NULL OR g.storedPath = :e')->setParameter('e', '');
        }
        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $counts = $this->processEntities($io, $qb->getQuery()->getResult(), fn (Game $g) => $this->mirrorCoverEntity(
            $g->getCoverUrl(),
            $g->getStoredPath(),
            ImageMediaType::GAME,
            'game-' . $g->getId(),
            fn (?string $p) => $g->setStoredPath($p),
            $force,
            $dryRun
        ));

        $imgQb = $this->em->createQueryBuilder()->select('i')->from(GameImage::class, 'i')->orderBy('i.id', 'ASC');
        if (!$force) {
            $imgQb->andWhere('i.url IS NOT NULL AND i.url != :e AND (i.filename IS NULL OR i.filename = :e2)')
                ->setParameter('e', '')->setParameter('e2', '');
        }
        if ($limit) {
            $imgQb->setMaxResults($limit);
        }

        $ok = $counts[0];
        $skip = $counts[1];
        $fail = $counts[2];

        foreach ($imgQb->getQuery()->getResult() as $img) {
            $result = $this->mirrorGalleryImage($img, $force, $dryRun);
            $this->tally($result, $ok, $skip, $fail);
        }

        $qb2 = $this->em->createQueryBuilder()->select('u')->from(LienUserGame::class, 'u')->orderBy('u.id', 'ASC');
        if (!$force) {
            $qb2->andWhere('u.storedPath IS NULL OR u.storedPath = :e')->setParameter('e', '');
        }
        if ($limit) {
            $qb2->setMaxResults($limit);
        }

        $c2 = $this->processEntities($io, $qb2->getQuery()->getResult(), fn (LienUserGame $u) => $this->mirrorCoverEntity(
            $u->getImagePerso(),
            $u->getStoredPath(),
            ImageMediaType::GAME_USER,
            'game-user-' . $u->getId(),
            fn (?string $p) => $u->setStoredPath($p),
            $force,
            $dryRun
        ));

        return [$ok + $c2[0], $skip + $c2[1], $fail + $c2[2]];
    }

    private function mirrorGalleryImage(GameImage $img, bool $force, bool $dryRun): string
    {
        $url = $img->getUrl();
        if (!$url || !$this->imageStorage->isRemoteUrl($url)) {
            return 'skip';
        }

        if ($dryRun) {
            return 'ok';
        }

        $path = $this->imageStorage->mirrorToStorage(
            $url,
            ImageMediaType::GAME_GALLERY,
            'game-img-' . $img->getId(),
            $img->getFilename() ? '/uploads/' . ImageMediaType::GAME_GALLERY . '/' . $img->getFilename() : null,
            $force
        );

        if ($path && preg_match('#/([^/]+)$#', $path, $m)) {
            $img->setFilename($m[1]);

            return 'ok';
        }

        return 'fail';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function processBricks(SymfonyStyle $io, bool $force, bool $dryRun, ?int $limit): array
    {
        $qb = $this->em->createQueryBuilder()->select('i')->from(BrickImage::class, 'i')->orderBy('i.id', 'ASC');
        $qb->andWhere('i.url IS NOT NULL AND i.url != :e')->setParameter('e', '');
        if (!$force) {
            $qb->andWhere('i.filename IS NULL OR i.filename = :e2')->setParameter('e2', '');
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $ok = $skip = $fail = 0;
        foreach ($qb->getQuery()->getResult() as $img) {
            $url = $img->getUrl();
            if (!$url) {
                ++$skip;
                continue;
            }
            $source = $this->imageStorage->isRemoteUrl($url) ? $url : $url;
            if ($dryRun) {
                ++$ok;
                continue;
            }
            $existing = $img->getFilename() ? '/uploads/' . ImageMediaType::BRICK . '/' . $img->getFilename() : null;
            $path = $this->imageStorage->mirrorToStorage($source, ImageMediaType::BRICK, 'brick-' . $img->getId(), $existing, $force);
            if ($path && preg_match('#/([^/]+)$#', $path, $m)) {
                $img->setFilename($m[1]);
                ++$ok;
            } else {
                ++$fail;
            }
        }

        return [$ok, $skip, $fail];
    }

    /**
     * @param callable(object): string $processor return ok|skip|fail
     * @param object[] $entities
     * @return array{0: int, 1: int, 2: int}
     */
    private function processEntities(SymfonyStyle $io, array $entities, callable $processor): array
    {
        $ok = $skip = $fail = 0;
        foreach ($entities as $entity) {
            $result = $processor($entity);
            $this->tally($result, $ok, $skip, $fail);
        }

        return [$ok, $skip, $fail];
    }

    private function mirrorCoverEntity(
        ?string $coverUrl,
        ?string $storedPath,
        string $mediaType,
        string $basename,
        callable $setter,
        bool $force,
        bool $dryRun
    ): string {
        if ($coverUrl === null || $coverUrl === '') {
            return 'skip';
        }

        if (!$force && $storedPath && $this->imageStorage->fileExistsForWebPath($storedPath)) {
            return 'skip';
        }

        $source = $coverUrl;
        if (!$this->imageStorage->isRemoteUrl($coverUrl) && !$this->imageStorage->isLocalWebPath($coverUrl)) {
            $source = '/uploads/' . $mediaType . '/' . ltrim($coverUrl, '/');
        }

        if ($dryRun) {
            return 'ok';
        }

        $path = $this->imageStorage->mirrorToStorage($source, $mediaType, $basename, $storedPath, $force);
        if ($path) {
            $setter($path);

            return 'ok';
        }

        return 'fail';
    }

    private function tally(string $result, int &$ok, int &$skip, int &$fail): void
    {
        match ($result) {
            'ok' => ++$ok,
            'skip' => ++$skip,
            default => ++$fail,
        };
    }

    private function readBlob(mixed $blob): ?string
    {
        if ($blob === null) {
            return null;
        }
        if (is_resource($blob)) {
            rewind($blob);
            $content = stream_get_contents($blob);

            return $content !== false && $content !== '' ? $content : null;
        }
        if (is_string($blob) && $blob !== '') {
            return $blob;
        }

        return null;
    }
}

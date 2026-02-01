<?php
// Test du service Puppeteer en PRODUCTION
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$apiKey = $_ENV['PUPPETEER_API_KEY'] ?? 'non-defini';
$serviceUrl = $_ENV['PUPPETEER_SERVICE_URL'] ?? 'non-defini';
$isbn = '9782344051504';

echo "=================================\n";
echo "Test Puppeteer - PRODUCTION\n";
echo "=================================\n\n";
echo "Configuration:\n";
echo "- Service URL: {$serviceUrl}\n";
echo "- API Key: " . substr($apiKey, 0, 8) . "...\n";
echo "- ISBN test: {$isbn}\n\n";

if ($serviceUrl === 'non-defini' || !$serviceUrl) {
    echo "❌ ERREUR: PUPPETEER_SERVICE_URL non défini dans .env\n";
    exit(1);
}

$url = "{$serviceUrl}/scrape/all?isbn={$isbn}";

$context = stream_context_create([
    'http' => [
        'timeout' => 120,
        'ignore_errors' => true,
        'header' => "X-API-Key: {$apiKey}\r\n"
    ]
]);

echo "Appel API: {$url}\n\n";

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "❌ ERREUR: Service Puppeteer injoignable\n";
    echo "Vérifications:\n";
    echo "1. Le service est-il démarré ? → pm2 list\n";
    echo "2. Le port 3000 écoute-t-il ? → netstat -tuln | grep 3000\n";
    echo "3. Test direct: curl -H 'X-API-Key: {$apiKey}' '{$serviceUrl}/health'\n";
    exit(1);
}

echo "✅ Réponse reçue du service\n\n";

$data = json_decode($response, true);

if (!$data) {
    echo "❌ ERREUR: Réponse JSON invalide\n";
    echo "Réponse brute: " . substr($response, 0, 200) . "...\n";
    exit(1);
}

echo "Résultat:\n";
echo "- Success: " . ($data['success'] ? '✅ OUI' : '❌ NON') . "\n";
echo "- ISBN: " . ($data['isbn'] ?? 'N/A') . "\n";
echo "- Nombre d'images: " . (isset($data['images']) ? count($data['images']) : 0) . "\n\n";

if (!empty($data['images'])) {
    echo "Images trouvées:\n";
    foreach ($data['images'] as $i => $img) {
        echo "  " . ($i + 1) . ". [{$img['source']}] {$img['url']}\n";
    }
    echo "\n✅✅✅ Le service Puppeteer fonctionne PARFAITEMENT en production! ✅✅✅\n";
} else {
    echo "⚠️  Aucune image trouvée pour cet ISBN\n";
    if (isset($data['error'])) {
        echo "Erreur: {$data['error']}\n";
    }
}

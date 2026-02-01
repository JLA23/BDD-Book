<?php
// Test rapide pour vérifier les images en base
require __DIR__.'/config/bootstrap.php';

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

$livres = $em->getRepository(\App\Entity\Livre::class)->findBy([], ['id' => 'ASC'], 5);

echo "=== Test des images en base ===\n\n";

foreach ($livres as $livre) {
    echo "Livre ID: " . $livre->getId() . "\n";
    echo "Titre: " . $livre->getTitre() . "\n";
    
    $image = $livre->getImage();
    if ($image) {
        if (is_resource($image)) {
            $imageData = stream_get_contents($image);
            echo "Image: BLOB resource - Taille: " . strlen($imageData) . " octets\n";
            echo "Début (hex): " . bin2hex(substr($imageData, 0, 20)) . "\n";
        } else {
            echo "Image: Type inattendu - " . gettype($image) . "\n";
            if (is_string($image)) {
                echo "Longueur: " . strlen($image) . "\n";
                echo "Début: " . substr($image, 0, 50) . "\n";
            }
        }
    } else {
        echo "Image: NULL\n";
    }
    echo "\n";
}

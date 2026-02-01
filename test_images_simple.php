<?php
// Test simple avec PDO
$pdo = new PDO('mysql:host=localhost;dbname=DatabaseBook', 'server', 'Jla2302@');

$stmt = $pdo->query("SELECT id, titre, LENGTH(image) as image_size, LEFT(HEX(image), 40) as image_hex FROM livre WHERE image IS NOT NULL LIMIT 5");
$livres = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Test des images en base ===\n\n";

if (empty($livres)) {
    echo "AUCUNE IMAGE TROUVÉE EN BASE !\n";
    echo "Le problème est que les images ne sont pas du tout stockées.\n\n";
    
    // Vérifier un exemple de données dans la queue
    $stmt2 = $pdo->query("SELECT id, operation, LEFT(data, 200) as data_preview FROM sync_queue WHERE operation = 'INSERT' AND status = 'DONE' LIMIT 1");
    $queue = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($queue) {
        echo "Exemple de données dans la queue (DONE):\n";
        echo "ID: " . $queue['id'] . "\n";
        echo "Data: " . $queue['data_preview'] . "...\n";
    }
} else {
    foreach ($livres as $livre) {
        echo "Livre ID: " . $livre['id'] . "\n";
        echo "Titre: " . $livre['titre'] . "\n";
        echo "Taille image: " . $livre['image_size'] . " octets\n";
        echo "Début (hex): " . $livre['image_hex'] . "\n\n";
    }
}

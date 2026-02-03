<?php

/**
 * Script pour corriger les attributs Doctrine qui sont dans les commentaires
 */

$entityDir = __DIR__ . '/src/Entity';
$files = glob($entityDir . '/*.php');

foreach ($files as $file) {
    if (basename($file) === 'User.php') {
        continue; // User.php est déjà correct
    }
    
    echo "Fixing: " . basename($file) . "\n";
    
    $content = file_get_contents($file);
    $original = $content;
    
    // Supprimer les attributs qui sont dans les commentaires /** ... */
    // et les placer avant le commentaire
    
    // Pattern pour capturer les blocs de commentaires avec attributs
    $content = preg_replace_callback(
        '/\/\*\*\s*\n([^*]*\*\s*#\[ORM\\\\[^\]]+\][^\n]*\n)+\s*\*\/\s*\n/s',
        function($matches) {
            $block = $matches[0];
            
            // Extraire tous les attributs du commentaire
            preg_match_all('/#\[ORM\\\\[^\]]+\]/', $block, $attributes);
            
            // Supprimer les lignes avec attributs du commentaire
            $cleanComment = preg_replace('/\s*\*\s*#\[ORM\\\\[^\]]+\]\s*\n/', '', $block);
            
            // Si le commentaire ne contient plus que /** */ ou juste @var, le supprimer
            if (preg_match('/\/\*\*\s*\*\s*@var[^*]*\*\s*\*\//', $cleanComment) || 
                preg_match('/\/\*\*\s*\*\s*\*\//', $cleanComment)) {
                $cleanComment = '';
            }
            
            // Reconstruire avec attributs avant le commentaire
            $result = '';
            foreach ($attributes[0] as $attr) {
                $result .= $attr . "\n";
            }
            if ($cleanComment && trim($cleanComment) !== '/**  */') {
                $result .= $cleanComment;
            }
            
            return $result;
        },
        $content
    );
    
    // Nettoyer les commentaires vides restants
    $content = preg_replace('/\/\*\*\s*\*\s*@var[^*]*\*\s*\*\/\s*\n/', '', $content);
    $content = preg_replace('/\/\*\*\s*\*\s*\*\/\s*\n/', '', $content);
    
    // Corriger les attributs de classe qui sont dans les commentaires
    $content = preg_replace_callback(
        '/\/\*\*\s*\n\s*\*\s*[^\n]*\n\s*\*\s*\n(\s*\*\s*#\[ORM\\\\[^\]]+\]\s*\n)+\s*\*\/\s*\nclass/s',
        function($matches) {
            preg_match_all('/#\[ORM\\\\[^\]]+\]/', $matches[0], $attributes);
            
            $result = '';
            foreach ($attributes[0] as $attr) {
                $result .= $attr . "\n";
            }
            $result .= "class";
            
            return $result;
        },
        $content
    );
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "  ✓ Fixed\n";
    } else {
        echo "  - No changes needed\n";
    }
}

echo "\nDone! All entities fixed.\n";

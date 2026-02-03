<?php

/**
 * Script pour convertir les annotations Doctrine en attributs PHP 8
 */

$entityDir = __DIR__ . '/src/Entity';
$files = glob($entityDir . '/*.php');

foreach ($files as $file) {
    echo "Converting: " . basename($file) . "\n";
    
    $content = file_get_contents($file);
    $original = $content;
    
    // Convertir @ORM\Table
    $content = preg_replace('/@ORM\\\\Table\(name="([^"]+)"\)/', '#[ORM\\Table(name: \'$1\')]', $content);
    
    // Convertir @ORM\Entity
    $content = preg_replace('/@ORM\\\\Entity\(repositoryClass="([^"]+)"\)/', '#[ORM\\Entity(repositoryClass: $1::class)]', $content);
    $content = preg_replace('/@ORM\\\\Entity\(repositoryClass=([^)]+)\)/', '#[ORM\\Entity(repositoryClass: $1)]', $content);
    
    // Convertir @ORM\Id
    $content = preg_replace('/@ORM\\\\Id\(\)/', '#[ORM\\Id]', $content);
    $content = preg_replace('/@ORM\\\\Id/', '#[ORM\\Id]', $content);
    
    // Convertir @ORM\GeneratedValue
    $content = preg_replace('/@ORM\\\\GeneratedValue\(strategy="AUTO"\)/', '#[ORM\\GeneratedValue]', $content);
    $content = preg_replace('/@ORM\\\\GeneratedValue\(\)/', '#[ORM\\GeneratedValue]', $content);
    $content = preg_replace('/@ORM\\\\GeneratedValue/', '#[ORM\\GeneratedValue]', $content);
    
    // Convertir @ORM\Column simple
    $content = preg_replace_callback(
        '/@ORM\\\\Column\(([^)]+)\)/',
        function($matches) {
            $params = $matches[1];
            // Remplacer name="..." par name: '...'
            $params = preg_replace('/name="([^"]+)"/', "name: '$1'", $params);
            // Remplacer type="..." par type: '...'
            $params = preg_replace('/type="([^"]+)"/', "type: '$1'", $params);
            // Remplacer length=... par length: ...
            $params = preg_replace('/length=(\d+)/', 'length: $1', $params);
            // Remplacer nullable=true/false
            $params = preg_replace('/nullable=true/', 'nullable: true', $params);
            $params = preg_replace('/nullable=false/', 'nullable: false', $params);
            // Remplacer unique=true/false
            $params = preg_replace('/unique=true/', 'unique: true', $params);
            $params = preg_replace('/unique=false/', 'unique: false', $params);
            
            return '#[ORM\\Column(' . $params . ')]';
        },
        $content
    );
    
    // Convertir @ORM\ManyToOne
    $content = preg_replace_callback(
        '/@ORM\\\\ManyToOne\(targetEntity="([^"]+)"\)/',
        function($matches) {
            return '#[ORM\\ManyToOne(targetEntity: ' . $matches[1] . '::class)]';
        },
        $content
    );
    
    // Convertir @ORM\OneToMany
    $content = preg_replace_callback(
        '/@ORM\\\\OneToMany\(targetEntity="([^"]+)", mappedBy="([^"]+)"(?:, cascade=\{([^}]+)\})?\)/',
        function($matches) {
            $result = '#[ORM\\OneToMany(targetEntity: ' . $matches[1] . '::class, mappedBy: \'' . $matches[2] . '\'';
            if (isset($matches[3]) && $matches[3]) {
                $cascade = str_replace('"', '\'', $matches[3]);
                $result .= ', cascade: [' . $cascade . ']';
            }
            $result .= ')]';
            return $result;
        },
        $content
    );
    
    // Convertir @ORM\JoinColumn
    $content = preg_replace_callback(
        '/@ORM\\\\JoinColumn\(([^)]+)\)/',
        function($matches) {
            $params = $matches[1];
            $params = preg_replace('/name="([^"]+)"/', "name: '$1'", $params);
            $params = preg_replace('/referencedColumnName="([^"]+)"/', "referencedColumnName: '$1'", $params);
            $params = preg_replace('/nullable=true/', 'nullable: true', $params);
            $params = preg_replace('/nullable=false/', 'nullable: false', $params);
            
            return '#[ORM\\JoinColumn(' . $params . ')]';
        },
        $content
    );
    
    // Supprimer les commentaires vides après conversion
    $content = preg_replace('/\/\*\*\s*\*\s*@var[^\n]*\n\s*\*\s*\*\/\s*\n/', '', $content);
    $content = preg_replace('/\/\*\*\s*\*\s*\*\/\s*\n/', '', $content);
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "  ✓ Converted\n";
    } else {
        echo "  - No changes needed\n";
    }
}

echo "\nDone! All entities converted to PHP 8 attributes.\n";

#!/bin/bash

# Script de resynchronisation complète avec images base64
# Ce script purge tout et relance la synchronisation avec le nouveau système

echo "=========================================="
echo "Resynchronisation complète avec images"
echo "=========================================="
echo ""

# Demander confirmation
read -p "Cette opération va SUPPRIMER tous les livres. Continuer ? (oui/non) : " confirm
if [ "$confirm" != "oui" ]; then
    echo "Opération annulée."
    exit 0
fi

echo ""
echo "1/4 - Purge de DatabaseBook (livres)..."
mysql -u server -p DatabaseBook < RecoverBDD_Java_V3/sql/purge_livres.sql
if [ $? -ne 0 ]; then
    echo "ERREUR lors de la purge de DatabaseBook"
    exit 1
fi

echo ""
echo "2/4 - Purge de DatabaseBookRef (base temporaire + queue)..."
mysql -u server -p DatabaseBookRef < RecoverBDD_Java_V3/sql/purge_databasebookref.sql
if [ $? -ne 0 ]; then
    echo "ERREUR lors de la purge de DatabaseBookRef"
    exit 1
fi

echo ""
echo "3/4 - Synchronisation Java (création de la queue avec base64)..."
cd RecoverBDD_Java_V3
java -jar RecoverBDD-Books.jar
if [ $? -ne 0 ]; then
    echo "ERREUR lors de l'exécution du JAR Java"
    exit 1
fi
cd ..

echo ""
echo "4/4 - Traitement de la queue en PHP (décodage base64 -> BLOB)..."
php bin/console RecoverBDD_V3
if [ $? -ne 0 ]; then
    echo "ERREUR lors du traitement de la queue"
    exit 1
fi

echo ""
echo "=========================================="
echo "✓ Synchronisation terminée avec succès !"
echo "=========================================="
echo ""
echo "Les images devraient maintenant s'afficher correctement."

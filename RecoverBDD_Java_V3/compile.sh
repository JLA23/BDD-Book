#!/bin/bash
# Compilation du projet RecoverBDD-Books V3

# Créer le dossier bin s'il n'existe pas
mkdir -p bin

# Compiler
javac -cp "lib/*" -d bin src/Main.java

# Créer le JAR
jar cfm RecoverBDD-Books.jar MANIFEST.MF -C bin .

echo "Compilation terminée"

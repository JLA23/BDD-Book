-- Script de purge complète de DatabaseBookRef (base temporaire)
-- Cette base est utilisée comme copie de référence depuis Access

-- ============================================
-- ATTENTION : Ce script vide TOUTE la base DatabaseBookRef !
-- Cette base sera recréée par le programme Java à chaque exécution
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Tables principales
TRUNCATE TABLE Monnaie;
TRUNCATE TABLE Matiere;
TRUNCATE TABLE Categorie;
TRUNCATE TABLE Etat;
TRUNCATE TABLE Pays;
TRUNCATE TABLE Couleur;
TRUNCATE TABLE Date;
TRUNCATE TABLE Devises;
TRUNCATE TABLE Historique;
TRUNCATE TABLE Sequence;
TRUNCATE TABLE sync_queue;
TRUNCATE TABLE Traitement;



-- Table de traitement
TRUNCATE TABLE Traitement;


-- Réinitialiser les auto-increment
ALTER TABLE Monnaie AUTO_INCREMENT = 1;
ALTER TABLE Matiere AUTO_INCREMENT = 1;
ALTER TABLE Categorie AUTO_INCREMENT = 1;
ALTER TABLE Etat AUTO_INCREMENT = 1;
ALTER TABLE Pays AUTO_INCREMENT = 1;
ALTER TABLE Traitement AUTO_INCREMENT = 1;
ALTER TABLE Couleur AUTO_INCREMENT = 1;
ALTER TABLE Date AUTO_INCREMENT = 1;
ALTER TABLE Devises AUTO_INCREMENT = 1;
ALTER TABLE Historique AUTO_INCREMENT = 1;
ALTER TABLE Sequence AUTO_INCREMENT = 1;
ALTER TABLE sync_queue AUTO_INCREMENT = 1;


SET FOREIGN_KEY_CHECKS = 1;

-- Vérification
SELECT 'Monnaie' as Table_Name, COUNT(*) as Count FROM Monnaie
UNION ALL
SELECT 'Matiere', COUNT(*) FROM Matiere
UNION ALL
SELECT 'Categorie', COUNT(*) FROM Categorie
UNION ALL
SELECT 'Etat', COUNT(*) FROM Etat
UNION ALL
SELECT 'Pays', COUNT(*) FROM Pays
UNION ALL
SELECT 'Traitement', COUNT(*) FROM Traitement;

SELECT 'DatabaseBookRef vidée - Prête pour resynchronisation' as Status;

-- Script de purge COMPLÈTE du système de synchronisation
-- Vide DatabaseBook ET DatabaseBookRef pour repartir de zéro

-- ============================================
-- ATTENTION : Ce script supprime TOUTES les données livres !
-- Faire une sauvegarde complète avant :
-- mysqldump -u server -p --databases DatabaseBook DatabaseBookRef > backup_complet_$(date +%Y%m%d_%H%M%S).sql
-- ============================================

-- ============================================
-- PARTIE 1 : DatabaseBook (base principale Symfony)
-- ============================================

USE DatabaseBook;

SELECT '=== PURGE DatabaseBook ===' as Info;

SET FOREIGN_KEY_CHECKS = 0;

-- Supprimer les données livres
TRUNCATE TABLE lien_auteur_livre;
TRUNCATE TABLE lien_user_livre;
TRUNCATE TABLE livre;

-- Supprimer les auteurs orphelins
DELETE FROM auteur WHERE id NOT IN (SELECT DISTINCT auteur_id FROM lien_auteur_livre);

-- Nettoyer la queue et l'historique
TRUNCATE TABLE sync_queue;
TRUNCATE TABLE Historique;

-- Réinitialiser les auto-increment
ALTER TABLE lien_auteur_livre AUTO_INCREMENT = 1;
ALTER TABLE lien_user_livre AUTO_INCREMENT = 1;
ALTER TABLE livre AUTO_INCREMENT = 1;
ALTER TABLE sync_queue AUTO_INCREMENT = 1;
ALTER TABLE Historique AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Vérification DatabaseBook
SELECT 'Livres' as Table_Name, COUNT(*) as Count FROM livre
UNION ALL
SELECT 'Liens User-Livre', COUNT(*) FROM lien_user_livre
UNION ALL
SELECT 'Liens Auteur-Livre', COUNT(*) FROM lien_auteur_livre
UNION ALL
SELECT 'Auteurs', COUNT(*) FROM auteur
UNION ALL
SELECT 'Queue sync', COUNT(*) FROM sync_queue
UNION ALL
SELECT 'Historique', COUNT(*) FROM Historique;

-- Vérifier que les magazines sont intacts
SELECT 'Magazines (non touchés)' as Info, COUNT(*) as Count FROM kiosk_collec
UNION ALL
SELECT 'Numéros magazines (non touchés)', COUNT(*) FROM kiosk_num
UNION ALL
SELECT 'Liens User-Magazine (non touchés)', COUNT(*) FROM lien_kiosk_num_user;

-- ============================================
-- PARTIE 2 : DatabaseBookRef (base temporaire)
-- ============================================

USE DatabaseBookRef;

SELECT '=== PURGE DatabaseBookRef ===' as Info;

SET FOREIGN_KEY_CHECKS = 0;

-- Tables principales
TRUNCATE TABLE Monnaie;
TRUNCATE TABLE Matiere;
TRUNCATE TABLE Categorie;
TRUNCATE TABLE Etat;
TRUNCATE TABLE Pays;
TRUNCATE TABLE Traitement;

-- Historique si existe
TRUNCATE TABLE IF EXISTS Historique;

-- Réinitialiser les auto-increment
ALTER TABLE Monnaie AUTO_INCREMENT = 1;
ALTER TABLE Matiere AUTO_INCREMENT = 1;
ALTER TABLE Categorie AUTO_INCREMENT = 1;
ALTER TABLE Etat AUTO_INCREMENT = 1;
ALTER TABLE Pays AUTO_INCREMENT = 1;
ALTER TABLE Traitement AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Vérification DatabaseBookRef
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

-- ============================================
-- RÉSUMÉ
-- ============================================

SELECT '=== PURGE COMPLÈTE TERMINÉE ===' as Info;
SELECT 'DatabaseBook : Livres supprimés, Magazines préservés' as Status
UNION ALL
SELECT 'DatabaseBookRef : Complètement vidée'
UNION ALL
SELECT 'Prêt pour resynchronisation complète depuis Access';

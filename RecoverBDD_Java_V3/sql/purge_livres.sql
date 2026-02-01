-- Script de purge complète des données LIVRES uniquement
-- Réinitialise les ID auto-increment
-- NE TOUCHE PAS aux magazines (KioskCollec, KioskNum, LienKioskNumUser)

-- ============================================
-- ATTENTION : Ce script supprime TOUTES les données livres !
-- Faire une sauvegarde avant d'exécuter :
-- mysqldump -u server -p DatabaseBook > backup_before_purge_$(date +%Y%m%d_%H%M%S).sql
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Supprimer les liens entre auteurs et livres
TRUNCATE TABLE lien_auteur_livre;

-- 2. Supprimer les liens entre utilisateurs et livres
TRUNCATE TABLE lien_user_livre;

-- 3. Supprimer les livres
TRUNCATE TABLE livre;

TRUNCATE TABLE auteur;

TRUNCATE TABLE category;

TRUNCATE TABLE collection;

TRUNCATE TABLE edition;
-- 4. Supprimer les auteurs orphelins (optionnel - décommenter si souhaité)
-- DELETE FROM auteur WHERE id NOT IN (SELECT DISTINCT auteur_id FROM lien_auteur_livre);

-- 5. Réinitialiser les compteurs auto-increment
ALTER TABLE lien_auteur_livre AUTO_INCREMENT = 1;
ALTER TABLE lien_user_livre AUTO_INCREMENT = 1;
ALTER TABLE livre AUTO_INCREMENT = 1;

ALTER TABLE auteur AUTO_INCREMENT = 1;
ALTER TABLE category AUTO_INCREMENT = 1;
ALTER TABLE edition AUTO_INCREMENT = 1;


-- 6. Nettoyer la queue de synchronisation (éléments liés aux livres)
-- Garder uniquement les éléments en ERROR pour analyse
DELETE FROM sync_queue WHERE status IN ('DONE', 'PENDING', 'PROCESSING');

-- 7. Optionnel : Nettoyer l'historique des livres (garder les 30 derniers jours)
DELETE FROM Historique WHERE date_action < DATE_SUB(NOW(), INTERVAL 30 DAY);

SET FOREIGN_KEY_CHECKS = 1;

-- Vérification des suppressions
SELECT 'Livres restants' as Table_Name, COUNT(*) as Count FROM livre
UNION ALL
SELECT 'Liens User-Livre restants', COUNT(*) FROM lien_user_livre
UNION ALL
SELECT 'Liens Auteur-Livre restants', COUNT(*) FROM lien_auteur_livre
UNION ALL
SELECT 'Queue restante', COUNT(*) FROM sync_queue
UNION ALL
SELECT 'Historique restant', COUNT(*) FROM Historique;

-- Afficher les tables non touchées (magazines)
SELECT 'Magazines (non touchés)' as Info, COUNT(*) as Count FROM kiosk_collec
UNION ALL
SELECT 'Numéros magazines (non touchés)', COUNT(*) FROM kiosk_num
UNION ALL
SELECT 'Liens User-Magazine (non touchés)', COUNT(*) FROM lien_kiosk_num_user;

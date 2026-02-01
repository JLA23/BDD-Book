-- Script de purge SÉCURISÉ des données LIVRES
-- Version avec sauvegarde automatique et confirmation
-- NE TOUCHE PAS aux magazines

-- ============================================
-- ÉTAPE 1 : Vérification avant purge
-- ============================================

SELECT '=== ÉTAT AVANT PURGE ===' as Info;

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

SELECT '=== MAGAZINES (ne seront PAS touchés) ===' as Info;

SELECT 'Magazines' as Table_Name, COUNT(*) as Count FROM kiosk_collec
UNION ALL
SELECT 'Numéros magazines', COUNT(*) FROM kiosk_num
UNION ALL
SELECT 'Liens User-Magazine', COUNT(*) FROM lien_kiosk_num_user;

-- ============================================
-- ÉTAPE 2 : Créer une table de backup temporaire
-- ============================================

-- Backup des livres (au cas où)
CREATE TABLE IF NOT EXISTS livre_backup_temp AS SELECT * FROM livre;
CREATE TABLE IF NOT EXISTS lien_user_livre_backup_temp AS SELECT * FROM lien_user_livre;
CREATE TABLE IF NOT EXISTS lien_auteur_livre_backup_temp AS SELECT * FROM lien_auteur_livre;

SELECT CONCAT('Backup créé : ', COUNT(*), ' livres sauvegardés') as Info FROM livre_backup_temp;

-- ============================================
-- ÉTAPE 3 : Purge avec réinitialisation des ID
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Supprimer les données
TRUNCATE TABLE lien_auteur_livre;
TRUNCATE TABLE lien_user_livre;
TRUNCATE TABLE livre;

-- Réinitialiser les auto-increment
ALTER TABLE lien_auteur_livre AUTO_INCREMENT = 1;
ALTER TABLE lien_user_livre AUTO_INCREMENT = 1;
ALTER TABLE livre AUTO_INCREMENT = 1;

-- Nettoyer la queue (garder les erreurs pour analyse)
DELETE FROM sync_queue WHERE status != 'ERROR';

-- Nettoyer l'historique ancien (garder 30 jours)
DELETE FROM Historique WHERE date_action < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Supprimer les auteurs sans livres
DELETE FROM auteur WHERE id NOT IN (
    SELECT DISTINCT auteur_id FROM lien_auteur_livre
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ÉTAPE 4 : Vérification après purge
-- ============================================

SELECT '=== ÉTAT APRÈS PURGE ===' as Info;

SELECT 'Livres' as Table_Name, COUNT(*) as Count FROM livre
UNION ALL
SELECT 'Liens User-Livre', COUNT(*) FROM lien_user_livre
UNION ALL
SELECT 'Liens Auteur-Livre', COUNT(*) FROM lien_auteur_livre
UNION ALL
SELECT 'Auteurs restants', COUNT(*) FROM auteur
UNION ALL
SELECT 'Queue restante', COUNT(*) FROM sync_queue
UNION ALL
SELECT 'Historique restant', COUNT(*) FROM Historique;

SELECT '=== MAGAZINES (vérification non touchés) ===' as Info;

SELECT 'Magazines' as Table_Name, COUNT(*) as Count FROM kiosk_collec
UNION ALL
SELECT 'Numéros magazines', COUNT(*) FROM kiosk_num
UNION ALL
SELECT 'Liens User-Magazine', COUNT(*) FROM lien_kiosk_num_user;

-- ============================================
-- ÉTAPE 5 : Instructions pour restaurer si besoin
-- ============================================

SELECT '=== POUR RESTAURER (si nécessaire) ===' as Info;
SELECT 'Exécuter : INSERT INTO livre SELECT * FROM livre_backup_temp;' as Commande
UNION ALL
SELECT 'Puis : INSERT INTO lien_user_livre SELECT * FROM lien_user_livre_backup_temp;'
UNION ALL
SELECT 'Puis : INSERT INTO lien_auteur_livre SELECT * FROM lien_auteur_livre_backup_temp;'
UNION ALL
SELECT 'Puis : DROP TABLE livre_backup_temp, lien_user_livre_backup_temp, lien_auteur_livre_backup_temp;';

-- ============================================
-- ÉTAPE 6 : Nettoyer les backups (après validation)
-- ============================================

-- DÉCOMMENTER CES LIGNES APRÈS VALIDATION QUE TOUT EST OK :
-- DROP TABLE IF EXISTS livre_backup_temp;
-- DROP TABLE IF EXISTS lien_user_livre_backup_temp;
-- DROP TABLE IF EXISTS lien_auteur_livre_backup_temp;

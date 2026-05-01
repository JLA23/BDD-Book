-- ============================================================
-- Supprime les colonnes chaîne sur lien_user_game
-- (console, type_edition, store) — remplacées par console_id,
-- type_edition_id, store_id.
--
-- Équivalent Doctrine : Version20260503143000
--
-- AVANT :
--   1. Sauvegarde BDD.
--   2. Vérifier que les FK sont remplies là où vous en avez besoin :
--        SELECT COUNT(*) FROM lien_user_game WHERE console_id IS NULL;
--        SELECT COUNT(*) FROM lien_user_game WHERE type_edition_id IS NULL;
--      (store_id peut rester NULL pour les éditions physiques.)
--   3. Si besoin, exécuter d’abord les UPDATE du fichier prod_jeux_etapes_simple.sql
--      (étapes 6 → 7) ou : php bin/console app:migrate-game-entities
--
-- Serveurs récents (MySQL >= 8.0.29, MariaDB >= 10.8.1) : utilisez le bloc A (IF EXISTS).
-- Anciens serveurs : bloc B uniquement ; ignorer manuellement « Unknown column » si déjà fait.
-- ============================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------- Bloc A : ré-exécutable sans erreur si colonnes déjà absentes ----------
ALTER TABLE lien_user_game DROP COLUMN IF EXISTS console;
ALTER TABLE lien_user_game DROP COLUMN IF EXISTS type_edition;
ALTER TABLE lien_user_game DROP COLUMN IF EXISTS store;

-- ---------- Bloc B (anciens MySQL / MariaDB sans IF EXISTS sur DROP COLUMN) ----------
-- ALTER TABLE lien_user_game DROP COLUMN console;
-- ALTER TABLE lien_user_game DROP COLUMN type_edition;
-- ALTER TABLE lien_user_game DROP COLUMN store;

-- ---------- Rollback manuel (recréer les colonnes vides) — rare ----------
-- ALTER TABLE lien_user_game ADD COLUMN console VARCHAR(100) DEFAULT NULL;
-- ALTER TABLE lien_user_game ADD COLUMN type_edition VARCHAR(20) DEFAULT NULL;
-- ALTER TABLE lien_user_game ADD COLUMN store VARCHAR(100) DEFAULT NULL;

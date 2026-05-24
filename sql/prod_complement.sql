-- ============================================================
-- Compléments schéma — stored_path, magazines, briques, IGDB
--
-- Avant tout : sauvegarde complète de la base.
-- Erreurs « duplicate column name » ou « duplicate key name » → ignorer, passer à la suite.
-- Charset : utf8mb4.
-- ============================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- ÉTAPE 1 — stored_path (images locales, URL source conservée)
-- ------------------------------------------------------------

ALTER TABLE livre ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE game ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE kiosk_collec ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE kiosk_num ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE lien_user_game ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE dvd ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE musique ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE dvd_user_collection ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE musique_user_collection ADD COLUMN stored_path VARCHAR(500) DEFAULT NULL;


-- ------------------------------------------------------------
-- ÉTAPE 2 — Magazines : prix et date d’achat propriétaire
-- ------------------------------------------------------------

ALTER TABLE lien_kiosk_num_user ADD COLUMN prix_achat NUMERIC(10, 2) DEFAULT NULL;
ALTER TABLE lien_kiosk_num_user ADD COLUMN date_achat DATE DEFAULT NULL;


-- ------------------------------------------------------------
-- ÉTAPE 3 — Briques : EAN sur les sets
-- ------------------------------------------------------------

ALTER TABLE brick_set ADD COLUMN ean VARCHAR(50) DEFAULT NULL;


-- ------------------------------------------------------------
-- ÉTAPE 4 — IGDB : correction des IDs plateforme (game_console)
-- Libère d’abord les valeurs en collision, puis réassigne.
-- ------------------------------------------------------------

UPDATE game_console SET igdb_platform_id = NULL
WHERE code IN ('PS3', 'PS2', 'XOne', 'X360', 'Xbox', '3DS');

UPDATE game_console SET igdb_platform_id = 9  WHERE code = 'PS3';
UPDATE game_console SET igdb_platform_id = 8  WHERE code = 'PS2';
UPDATE game_console SET igdb_platform_id = 49 WHERE code = 'XOne';
UPDATE game_console SET igdb_platform_id = 12 WHERE code = 'X360';
UPDATE game_console SET igdb_platform_id = 11 WHERE code = 'Xbox';
UPDATE game_console SET igdb_platform_id = 37 WHERE code = '3DS';


-- ------------------------------------------------------------
-- ÉTAPE 5 — Vérification (optionnel)
-- ------------------------------------------------------------
-- SELECT table_name, column_name FROM information_schema.columns
--   WHERE table_schema = DATABASE() AND column_name = 'stored_path';
-- SELECT code, igdb_platform_id FROM game_console WHERE code IN ('PS2','PS3');

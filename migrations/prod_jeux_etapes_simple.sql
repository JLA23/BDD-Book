-- ============================================================
-- Jeux vidéo — mise à jour prod en SQL, étapes simples (MySQL / MariaDB)
--
-- Avant tout : sauvegarde complète de la base.
-- Si une étape renvoie « duplicate », « already exists », ignorez et passez à la suivante.
-- Charset : utf8mb4.
-- ============================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- ÉTAPE 1 — Tables de référence (si elles n’existent pas encore)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS game_console (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    icone VARCHAR(100) DEFAULT NULL,
    couleur VARCHAR(20) DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    actif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_console_alias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(150) NOT NULL,
    console_id INT NOT NULL,
    UNIQUE KEY uniq_game_console_alias_libelle (libelle),
    CONSTRAINT FK_game_console_alias_console FOREIGN KEY (console_id)
        REFERENCES game_console (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_type_edition (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    nom VARCHAR(50) NOT NULL,
    position INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_store (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    icone VARCHAR(100) DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    actif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- ÉTAPE 2 — Consoles (upsert par code)
-- ------------------------------------------------------------

INSERT INTO game_console (code, nom, icone, couleur, position) VALUES
('PS5',      'PlayStation 5',      'fab fa-playstation', '#003791', 1),
('PS4',      'PlayStation 4',      'fab fa-playstation', '#003791', 2),
('PS3',      'PlayStation 3',      'fab fa-playstation', '#003791', 3),
('PS2',      'PlayStation 2',      'fab fa-playstation', '#003791', 4),
('PS1',      'PlayStation',        'fab fa-playstation', '#003791', 5),
('PSVita',   'PS Vita',            'fab fa-playstation', '#003791', 6),
('PSP',      'PSP',                'fab fa-playstation', '#003791', 7),
('XSX',      'Xbox Series X',      'fab fa-xbox',        '#107C10', 10),
('XOne',     'Xbox One',           'fab fa-xbox',        '#107C10', 11),
('X360',     'Xbox 360',           'fab fa-xbox',        '#107C10', 12),
('Xbox',     'Xbox',               'fab fa-xbox',        '#107C10', 13),
('Switch',   'Nintendo Switch',    'fas fa-gamepad',     '#E60012', 20),
('WiiU',     'Wii U',              'fas fa-gamepad',     '#E60012', 21),
('Wii',      'Wii',                'fas fa-gamepad',     '#E60012', 22),
('GameCube', 'GameCube',           'fas fa-gamepad',     '#E60012', 23),
('N64',      'Nintendo 64',        'fas fa-gamepad',     '#E60012', 24),
('3DS',      'Nintendo 3DS',       'fas fa-gamepad',     '#E60012', 25),
('DS',       'Nintendo DS',        'fas fa-gamepad',     '#E60012', 26),
('GBA',      'Game Boy Advance',   'fas fa-gamepad',     '#E60012', 27),
('GB',       'Game Boy',           'fas fa-gamepad',     '#E60012', 28),
('PC',       'PC',                 'fas fa-desktop',     '#333333', 30),
('Mac',      'Mac',                'fab fa-apple',       '#555555', 31),
('Linux',    'Linux',              'fab fa-linux',       '#FCC624', 32),
('Android',  'Android',            'fab fa-android',     '#3DDC84', 33),
('iOS',      'iOS',                'fab fa-apple',       '#555555', 34)
ON DUPLICATE KEY UPDATE
    nom = VALUES(nom), icone = VALUES(icone), couleur = VALUES(couleur), position = VALUES(position);


-- ------------------------------------------------------------
-- ÉTAPE 3 — IGDB : colonne + index + valeurs par code console
-- (erreur si colonne ou index déjà là → passer à la suite)
-- ------------------------------------------------------------

ALTER TABLE game_console ADD COLUMN igdb_platform_id INT DEFAULT NULL;
CREATE UNIQUE INDEX UNIQ_GAME_CONSOLE_IGDB_PLATFORM ON game_console (igdb_platform_id);

UPDATE game_console SET igdb_platform_id = 167 WHERE code = 'PS5';
UPDATE game_console SET igdb_platform_id = 48  WHERE code = 'PS4';
UPDATE game_console SET igdb_platform_id = 16  WHERE code = 'PS3';
UPDATE game_console SET igdb_platform_id = 9   WHERE code = 'PS2';
UPDATE game_console SET igdb_platform_id = 7   WHERE code = 'PS1';
UPDATE game_console SET igdb_platform_id = 46  WHERE code = 'PSVita';
UPDATE game_console SET igdb_platform_id = 38  WHERE code = 'PSP';
UPDATE game_console SET igdb_platform_id = 169 WHERE code = 'XSX';
UPDATE game_console SET igdb_platform_id = 12  WHERE code = 'XOne';
UPDATE game_console SET igdb_platform_id = 11  WHERE code = 'X360';
UPDATE game_console SET igdb_platform_id = 1   WHERE code = 'Xbox';
UPDATE game_console SET igdb_platform_id = 130 WHERE code = 'Switch';
UPDATE game_console SET igdb_platform_id = 41  WHERE code = 'WiiU';
UPDATE game_console SET igdb_platform_id = 5   WHERE code = 'Wii';
UPDATE game_console SET igdb_platform_id = 21  WHERE code = 'GameCube';
UPDATE game_console SET igdb_platform_id = 4   WHERE code = 'N64';
UPDATE game_console SET igdb_platform_id = 18  WHERE code = '3DS';
UPDATE game_console SET igdb_platform_id = 20  WHERE code = 'DS';
UPDATE game_console SET igdb_platform_id = 24  WHERE code = 'GBA';
UPDATE game_console SET igdb_platform_id = 6   WHERE code = 'PC';
UPDATE game_console SET igdb_platform_id = 14  WHERE code = 'Mac';
UPDATE game_console SET igdb_platform_id = 3   WHERE code = 'Linux';
UPDATE game_console SET igdb_platform_id = 34  WHERE code = 'Android';
UPDATE game_console SET igdb_platform_id = 39  WHERE code = 'iOS';


-- ------------------------------------------------------------
-- ÉTAPE 4 — Types d’édition et stores
-- ------------------------------------------------------------

INSERT INTO game_type_edition (code, nom, position) VALUES
('physique',  'Physique',   1),
('numerique', 'Numérique',  2)
ON DUPLICATE KEY UPDATE nom = VALUES(nom), position = VALUES(position);

INSERT INTO game_store (nom, icone, position) VALUES
('Steam',              'fab fa-steam',       1),
('Epic Games',         'fas fa-gamepad',     2),
('GOG',                'fas fa-gamepad',     3),
('PlayStation Store',  'fab fa-playstation', 4),
('Xbox Store',         'fab fa-xbox',        5),
('Nintendo eShop',     'fas fa-gamepad',     6),
('Ubisoft Connect',    'fas fa-gamepad',     7),
('EA App',             'fas fa-gamepad',     8),
('Battle.net',         'fas fa-gamepad',     9),
('Autre',              'fas fa-store',       99)
ON DUPLICATE KEY UPDATE icone = VALUES(icone), position = VALUES(position);


-- ------------------------------------------------------------
-- ÉTAPE 5 — Colonnes et clés étrangères sur lien_user_game
-- ------------------------------------------------------------

ALTER TABLE lien_user_game ADD COLUMN console_id INT DEFAULT NULL;
ALTER TABLE lien_user_game ADD COLUMN type_edition_id INT DEFAULT NULL;
ALTER TABLE lien_user_game ADD COLUMN store_id INT DEFAULT NULL;

ALTER TABLE lien_user_game
    ADD CONSTRAINT FK_lien_user_game_console FOREIGN KEY (console_id) REFERENCES game_console (id) ON DELETE SET NULL;
ALTER TABLE lien_user_game
    ADD CONSTRAINT FK_lien_user_game_type_edition FOREIGN KEY (type_edition_id) REFERENCES game_type_edition (id) ON DELETE SET NULL;
ALTER TABLE lien_user_game
    ADD CONSTRAINT FK_lien_user_game_store FOREIGN KEY (store_id) REFERENCES game_store (id) ON DELETE SET NULL;


-- ------------------------------------------------------------
-- ÉTAPE 6 — Remplir les alias (slugs IGDB / libellés)
--
-- Option A (recommandé) : après déploiement du code :
--   php bin/console app:seed-console-slug-aliases --env=prod
--
-- Option B : copier depuis migrations/game_entities_migration.sql
--   tout ce qui est entre le commentaire « -- 6. Alias (slugs IGDB »
--   et la ligne « -- 7. Types d'édition » (uniquement les INSERT ...).
-- ------------------------------------------------------------


-- ------------------------------------------------------------
-- ÉTAPE 7 — Rattrapage des lignes déjà en base (texte → FK)
-- Nécessite les colonnes lien_user_game.console, type_edition, store (avant suppression).
-- Si vous utilisez Doctrine : préférez la migration Version20260503143000 après avoir tout migré vers les FK.
-- ------------------------------------------------------------

UPDATE lien_user_game lug
INNER JOIN game_console_alias gca ON LOWER(TRIM(gca.libelle)) = LOWER(TRIM(lug.console))
SET lug.console_id = gca.console_id
WHERE lug.console IS NOT NULL AND TRIM(lug.console) <> '' AND lug.console_id IS NULL;

UPDATE lien_user_game lug
INNER JOIN game_console gc ON gc.code = lug.console
SET lug.console_id = gc.id
WHERE lug.console IS NOT NULL AND TRIM(lug.console) <> '' AND lug.console_id IS NULL;

UPDATE lien_user_game lug
INNER JOIN game_type_edition gte ON gte.code = lug.type_edition
SET lug.type_edition_id = gte.id
WHERE lug.type_edition IS NOT NULL AND lug.type_edition <> '' AND lug.type_edition_id IS NULL;

UPDATE lien_user_game lug
INNER JOIN game_store gs ON gs.nom = lug.store
SET lug.store_id = gs.id
WHERE lug.store IS NOT NULL AND lug.store <> '' AND lug.store_id IS NULL
  AND lug.type_edition = 'numerique';


-- ------------------------------------------------------------
-- ÉTAPE 8 — Vérification rapide (optionnel)
-- ------------------------------------------------------------
-- SELECT COUNT(*) FROM game_console;
-- SELECT COUNT(*) FROM game_console_alias;
-- Avant suppression des colonnes chaîne :
-- SELECT COUNT(*) FROM lien_user_game WHERE console IS NOT NULL AND console_id IS NULL;


-- ------------------------------------------------------------
-- ÉTAPE 9 — Supprimer les colonnes chaîne (après FK OK)
-- Exécuter : migrations/drop_lien_user_game_legacy_columns.sql
-- ------------------------------------------------------------

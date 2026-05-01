-- ============================================================
-- Migration manuelle : références jeux vidéo (consoles, types d'édition, stores)
-- et liaisons depuis lien_user_game.
--
-- À exécuter sur MySQL/MariaDB après sauvegarde. Adapter si colonnes déjà présentes.
-- ============================================================

-- 0. Colonne console sur lien_user_game (si votre schéma ne l’a pas encore)
-- Décommentez si nécessaire :
-- ALTER TABLE lien_user_game ADD COLUMN console VARCHAR(100) DEFAULT NULL;

-- 1. Table game_console (icône FontAwesome + couleur de fond du badge)
CREATE TABLE IF NOT EXISTS game_console (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    icone VARCHAR(100) DEFAULT NULL COMMENT 'Classe CSS FontAwesome',
    couleur VARCHAR(20) DEFAULT NULL COMMENT 'Couleur de fond (#hex)',
    position INT NOT NULL DEFAULT 0,
    actif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Alias / mapping : libellé brut → console canonique
CREATE TABLE IF NOT EXISTS game_console_alias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    libelle VARCHAR(150) NOT NULL COMMENT 'Texte matché (trim + compare insensible à la casse)',
    console_id INT NOT NULL,
    UNIQUE KEY uniq_game_console_alias_libelle (libelle),
    CONSTRAINT FK_game_console_alias_console FOREIGN KEY (console_id)
        REFERENCES game_console (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Types d’édition
CREATE TABLE IF NOT EXISTS game_type_edition (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    nom VARCHAR(50) NOT NULL,
    position INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Stores (principalement pour édition numérique)
CREATE TABLE IF NOT EXISTS game_store (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    icone VARCHAR(100) DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    actif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Peupler game_console
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
ON DUPLICATE KEY UPDATE nom=VALUES(nom), icone=VALUES(icone), couleur=VALUES(couleur), position=VALUES(position);

-- 6. Alias (slugs IGDB, libellés) → game_console — aligné sur src/Data/ConsoleSlugAliasSeedData.php
--     Ré-exécutable : ON DUPLICATE KEY UPDATE. Exécuter après le peuplement de game_console (étape 5).
--     Régénérer ce bloc : php scripts/emit-console-alias-sql.php

INSERT INTO game_console_alias (libelle, console_id)
SELECT 'playstation5', id FROM game_console WHERE code = 'PS5' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'ps5', id FROM game_console WHERE code = 'PS5' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PlayStation 5', id FROM game_console WHERE code = 'PS5' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Playstation 5', id FROM game_console WHERE code = 'PS5' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'playstation4', id FROM game_console WHERE code = 'PS4' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'ps4', id FROM game_console WHERE code = 'PS4' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PlayStation 4', id FROM game_console WHERE code = 'PS4' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Playstation 4', id FROM game_console WHERE code = 'PS4' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'playstation3', id FROM game_console WHERE code = 'PS3' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'ps3', id FROM game_console WHERE code = 'PS3' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PlayStation 3', id FROM game_console WHERE code = 'PS3' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'playstation2', id FROM game_console WHERE code = 'PS2' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'ps2', id FROM game_console WHERE code = 'PS2' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PlayStation 2', id FROM game_console WHERE code = 'PS2' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'playstation', id FROM game_console WHERE code = 'PS1' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'playstation1', id FROM game_console WHERE code = 'PS1' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'ps1', id FROM game_console WHERE code = 'PS1' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'psx', id FROM game_console WHERE code = 'PS1' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PlayStation', id FROM game_console WHERE code = 'PS1' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PSX', id FROM game_console WHERE code = 'PS1' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'playstationvita', id FROM game_console WHERE code = 'PSVita' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'psvita', id FROM game_console WHERE code = 'PSVita' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'vita', id FROM game_console WHERE code = 'PSVita' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PS Vita', id FROM game_console WHERE code = 'PSVita' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PlayStation Vita', id FROM game_console WHERE code = 'PSVita' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'playstationportable', id FROM game_console WHERE code = 'PSP' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'psp', id FROM game_console WHERE code = 'PSP' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PlayStation Portable', id FROM game_console WHERE code = 'PSP' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'xboxseriesx/s', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'xboxseriesx|s', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'xboxseriesx', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'xboxseries', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'seriesx', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'xsx', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Xbox Series X', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Xbox Series X|S', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Xbox Series X/S', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Series X', id FROM game_console WHERE code = 'XSX' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'xboxone', id FROM game_console WHERE code = 'XOne' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Xbox One', id FROM game_console WHERE code = 'XOne' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'xbox360', id FROM game_console WHERE code = 'X360' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Xbox 360', id FROM game_console WHERE code = 'X360' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'xbox', id FROM game_console WHERE code = 'Xbox' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'nintendoswitch', id FROM game_console WHERE code = 'Switch' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'switch', id FROM game_console WHERE code = 'Switch' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Nintendo Switch', id FROM game_console WHERE code = 'Switch' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'wiiu', id FROM game_console WHERE code = 'WiiU' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Wii U', id FROM game_console WHERE code = 'WiiU' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'wii', id FROM game_console WHERE code = 'Wii' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'nintendogamecube', id FROM game_console WHERE code = 'GameCube' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'gamecube', id FROM game_console WHERE code = 'GameCube' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'ngc', id FROM game_console WHERE code = 'GameCube' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'gc', id FROM game_console WHERE code = 'GameCube' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Nintendo GameCube', id FROM game_console WHERE code = 'GameCube' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'NGC', id FROM game_console WHERE code = 'GameCube' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'nintendo64', id FROM game_console WHERE code = 'N64' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'n64', id FROM game_console WHERE code = 'N64' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Nintendo 64', id FROM game_console WHERE code = 'N64' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'nintendo3ds', id FROM game_console WHERE code = '3DS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT '3ds', id FROM game_console WHERE code = '3DS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Nintendo 3DS', id FROM game_console WHERE code = '3DS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'nintendods', id FROM game_console WHERE code = 'DS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'nds', id FROM game_console WHERE code = 'DS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'ds', id FROM game_console WHERE code = 'DS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Nintendo DS', id FROM game_console WHERE code = 'DS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'gameboyadvance', id FROM game_console WHERE code = 'GBA' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'gba', id FROM game_console WHERE code = 'GBA' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Game Boy Advance', id FROM game_console WHERE code = 'GBA' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'gameboycolor', id FROM game_console WHERE code = 'GB' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'gbc', id FROM game_console WHERE code = 'GB' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'gameboy', id FROM game_console WHERE code = 'GB' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'gb', id FROM game_console WHERE code = 'GB' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Game Boy', id FROM game_console WHERE code = 'GB' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Game Boy Color', id FROM game_console WHERE code = 'GB' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'pc(microsoftwindows)', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'pcwindows', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'microsoftwindows', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'windows', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'win', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'pc', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'pc(microsoft windows)', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'pc(windows)', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PC (Microsoft Windows)', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'PC (Windows)', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Windows', id FROM game_console WHERE code = 'PC' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'macintosh', id FROM game_console WHERE code = 'Mac' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'macos', id FROM game_console WHERE code = 'Mac' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'mac', id FROM game_console WHERE code = 'Mac' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'Macintosh', id FROM game_console WHERE code = 'Mac' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'linux', id FROM game_console WHERE code = 'Linux' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'android', id FROM game_console WHERE code = 'Android' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'iphone', id FROM game_console WHERE code = 'iOS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'ipad', id FROM game_console WHERE code = 'iOS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
INSERT INTO game_console_alias (libelle, console_id)
SELECT 'ios', id FROM game_console WHERE code = 'iOS' LIMIT 1
ON DUPLICATE KEY UPDATE console_id = VALUES(console_id);
-- 7. Types d’édition
INSERT INTO game_type_edition (code, nom, position) VALUES
('physique',  'Physique',   1),
('numerique', 'Numérique',  2)
ON DUPLICATE KEY UPDATE nom=VALUES(nom), position=VALUES(position);

-- 8. Stores
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
ON DUPLICATE KEY UPDATE icone=VALUES(icone), position=VALUES(position);

-- 9. Colonnes FK sur lien_user_game
-- Ignorer l’erreur « duplicate column » / « Duplicate key name » si déjà appliqué.
ALTER TABLE lien_user_game ADD COLUMN console_id INT DEFAULT NULL;
ALTER TABLE lien_user_game ADD COLUMN type_edition_id INT DEFAULT NULL;
ALTER TABLE lien_user_game ADD COLUMN store_id INT DEFAULT NULL;

ALTER TABLE lien_user_game ADD CONSTRAINT FK_lien_user_game_console FOREIGN KEY (console_id) REFERENCES game_console (id) ON DELETE SET NULL;
ALTER TABLE lien_user_game ADD CONSTRAINT FK_lien_user_game_type_edition FOREIGN KEY (type_edition_id) REFERENCES game_type_edition (id) ON DELETE SET NULL;
ALTER TABLE lien_user_game ADD CONSTRAINT FK_lien_user_game_store FOREIGN KEY (store_id) REFERENCES game_store (id) ON DELETE SET NULL;

-- 10. Données existantes : console via alias puis via code exact
--     (colonnes lien_user_game.console / type_edition / store requises ; après FK complètes,
--      suppression possible via doctrine:migrations ou ALTER DROP COLUMN — voir Version20260503143000.)
UPDATE lien_user_game lug
INNER JOIN game_console_alias gca ON LOWER(TRIM(gca.libelle)) = LOWER(TRIM(lug.console))
SET lug.console_id = gca.console_id
WHERE lug.console IS NOT NULL AND TRIM(lug.console) <> '' AND lug.console_id IS NULL;

UPDATE lien_user_game lug
INNER JOIN game_console gc ON gc.code = lug.console
SET lug.console_id = gc.id
WHERE lug.console IS NOT NULL AND TRIM(lug.console) <> '' AND lug.console_id IS NULL;

-- 11. type_edition string → FK
UPDATE lien_user_game lug
INNER JOIN game_type_edition gte ON gte.code = lug.type_edition
SET lug.type_edition_id = gte.id
WHERE lug.type_edition IS NOT NULL AND lug.type_edition <> '' AND lug.type_edition_id IS NULL;

-- 12. store → FK (uniquement pour les lignes numériques)
UPDATE lien_user_game lug
INNER JOIN game_store gs ON gs.nom = lug.store
SET lug.store_id = gs.id
WHERE lug.store IS NOT NULL AND lug.store <> '' AND lug.store_id IS NULL
  AND lug.type_edition = 'numerique';

-- 13. IGDB : ID plateforme par console (filtres API / recherche IGDB)
-- Ignorer si colonne ou index déjà présents (voir aussi Doctrine Migration Version20260502110000).
ALTER TABLE game_console ADD COLUMN igdb_platform_id INT DEFAULT NULL;
CREATE UNIQUE INDEX UNIQ_GAME_CONSOLE_IGDB_PLATFORM ON game_console (igdb_platform_id);

UPDATE game_console SET igdb_platform_id = 167 WHERE code = 'PS5';
UPDATE game_console SET igdb_platform_id = 48 WHERE code = 'PS4';
UPDATE game_console SET igdb_platform_id = 16 WHERE code = 'PS3';
UPDATE game_console SET igdb_platform_id = 9 WHERE code = 'PS2';
UPDATE game_console SET igdb_platform_id = 7 WHERE code = 'PS1';
UPDATE game_console SET igdb_platform_id = 46 WHERE code = 'PSVita';
UPDATE game_console SET igdb_platform_id = 38 WHERE code = 'PSP';
UPDATE game_console SET igdb_platform_id = 169 WHERE code = 'XSX';
UPDATE game_console SET igdb_platform_id = 12 WHERE code = 'XOne';
UPDATE game_console SET igdb_platform_id = 11 WHERE code = 'X360';
UPDATE game_console SET igdb_platform_id = 1 WHERE code = 'Xbox';
UPDATE game_console SET igdb_platform_id = 130 WHERE code = 'Switch';
UPDATE game_console SET igdb_platform_id = 41 WHERE code = 'WiiU';
UPDATE game_console SET igdb_platform_id = 5 WHERE code = 'Wii';
UPDATE game_console SET igdb_platform_id = 21 WHERE code = 'GameCube';
UPDATE game_console SET igdb_platform_id = 4 WHERE code = 'N64';
UPDATE game_console SET igdb_platform_id = 18 WHERE code = '3DS';
UPDATE game_console SET igdb_platform_id = 20 WHERE code = 'DS';
UPDATE game_console SET igdb_platform_id = 24 WHERE code = 'GBA';
UPDATE game_console SET igdb_platform_id = 6 WHERE code = 'PC';
UPDATE game_console SET igdb_platform_id = 14 WHERE code = 'Mac';
UPDATE game_console SET igdb_platform_id = 3 WHERE code = 'Linux';
UPDATE game_console SET igdb_platform_id = 34 WHERE code = 'Android';
UPDATE game_console SET igdb_platform_id = 39 WHERE code = 'iOS';

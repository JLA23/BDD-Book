-- ============================================================
-- DVD & Musique — création des tables (MySQL / MariaDB)
--
-- Avant tout : sauvegarde complète de la base.
-- Ré-exécutable : CREATE TABLE IF NOT EXISTS.
-- Erreurs « duplicate » sur les clés étrangères → tables déjà OK, ignorer.
-- Charset : utf8mb4.
-- ============================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- ÉTAPE 1 — Catalogue DVD / Blu-ray
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS dvd (
    id INT AUTO_INCREMENT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    type VARCHAR(20) DEFAULT NULL COMMENT 'film ou serie',
    format VARCHAR(20) DEFAULT NULL COMMENT 'dvd, bluray, bluray4k',
    annee INT DEFAULT NULL,
    editeur VARCHAR(255) DEFAULT NULL,
    description LONGTEXT DEFAULT NULL,
    cover_url VARCHAR(500) DEFAULT NULL,
    external_id VARCHAR(100) DEFAULT NULL,
    edition VARCHAR(255) DEFAULT NULL,
    ean VARCHAR(20) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    stored_path VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- ÉTAPE 2 — Catalogue Musique (CD, vinyle, K7…)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS musique (
    id INT AUTO_INCREMENT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    artiste VARCHAR(255) DEFAULT NULL,
    format VARCHAR(30) DEFAULT NULL COMMENT 'cd, vinyle, k7, digital',
    type VARCHAR(30) DEFAULT NULL COMMENT 'album, single, compilation, ep',
    annee INT DEFAULT NULL,
    label VARCHAR(255) DEFAULT NULL,
    genre VARCHAR(100) DEFAULT NULL,
    description LONGTEXT DEFAULT NULL,
    cover_url VARCHAR(500) DEFAULT NULL,
    external_id VARCHAR(100) DEFAULT NULL,
    ean VARCHAR(50) DEFAULT NULL,
    tracklist LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    stored_path VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- ÉTAPE 3 — Collections utilisateur
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS dvd_user_collection (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    dvd_id INT NOT NULL,
    prix_achat NUMERIC(10, 2) DEFAULT NULL,
    date_achat DATE DEFAULT NULL,
    image_perso VARCHAR(500) DEFAULT NULL,
    commentaire LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    stored_path VARCHAR(500) DEFAULT NULL,
    INDEX IDX_555F09DAA76ED395 (user_id),
    INDEX IDX_555F09DA797185F6 (dvd_id),
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS musique_user_collection (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    musique_id INT NOT NULL,
    prix_achat NUMERIC(10, 2) DEFAULT NULL,
    date_achat DATE DEFAULT NULL,
    image_perso VARCHAR(500) DEFAULT NULL,
    commentaire LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    stored_path VARCHAR(500) DEFAULT NULL,
    INDEX IDX_562B0E3A76ED395 (user_id),
    INDEX IDX_562B0E325E254A1 (musique_id),
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- ÉTAPE 4 — Clés étrangères
-- Erreur « Duplicate foreign key constraint name » → déjà OK, ignorer.
-- ------------------------------------------------------------

ALTER TABLE dvd_user_collection
    ADD CONSTRAINT FK_555F09DAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id);

ALTER TABLE dvd_user_collection
    ADD CONSTRAINT FK_555F09DA797185F6 FOREIGN KEY (dvd_id) REFERENCES dvd (id) ON DELETE CASCADE;

ALTER TABLE musique_user_collection
    ADD CONSTRAINT FK_562B0E3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id);

ALTER TABLE musique_user_collection
    ADD CONSTRAINT FK_562B0E325E254A1 FOREIGN KEY (musique_id) REFERENCES musique (id) ON DELETE CASCADE;


-- ------------------------------------------------------------
-- ÉTAPE 5 — Vérification (optionnel)
-- ------------------------------------------------------------
-- SHOW TABLES LIKE 'dvd%';
-- SHOW TABLES LIKE 'musique%';
-- DESCRIBE dvd;
-- DESCRIBE musique;

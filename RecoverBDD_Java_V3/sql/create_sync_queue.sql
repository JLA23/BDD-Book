-- Table de queue pour la synchronisation
CREATE TABLE IF NOT EXISTS sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operation ENUM('INSERT', 'UPDATE', 'DELETE', 'TRANSFER') NOT NULL,
    seq VARCHAR(50) NOT NULL,
    col_type VARCHAR(50) NOT NULL,
    data JSON NOT NULL,
    hash_data VARCHAR(32),
    status ENUM('PENDING', 'PROCESSING', 'DONE', 'ERROR') DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    error_message TEXT,
    retry_count INT DEFAULT 0,
    INDEX idx_status (status),
    INDEX idx_seq_coltype (seq, col_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table d'historique améliorée
CREATE TABLE IF NOT EXISTS Historique (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(20) NOT NULL,
    seq VARCHAR(50),
    col_type VARCHAR(50),
    titre VARCHAR(500),
    isbn VARCHAR(100),
    old_col_type VARCHAR(50),
    new_col_type VARCHAR(50),
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    hash_before VARCHAR(32),
    hash_after VARCHAR(32),
    INDEX idx_action (action),
    INDEX idx_date (date_action),
    INDEX idx_seq_coltype (seq, col_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vue pour monitoring
CREATE OR REPLACE VIEW sync_queue_stats AS
SELECT 
    status,
    COUNT(*) as count,
    MIN(created_at) as oldest,
    MAX(created_at) as newest
FROM sync_queue
GROUP BY status;

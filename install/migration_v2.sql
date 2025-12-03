-- Migration v2 - Nouvelles fonctionnalités
-- Email, Finance, Tickets, Documents, Espace membre

-- Ajouter colonnes à users pour carte membre
ALTER TABLE users ADD COLUMN IF NOT EXISTS numero_membre VARCHAR(20) UNIQUE AFTER id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS carte_generee_le DATE AFTER photo;

-- Ajouter colonnes à inscriptions pour billets et liste d'attente
ALTER TABLE inscriptions ADD COLUMN IF NOT EXISTS ticket_code VARCHAR(32) UNIQUE AFTER id;
ALTER TABLE inscriptions ADD COLUMN IF NOT EXISTS liste_attente TINYINT(1) DEFAULT 0 AFTER statut;
ALTER TABLE inscriptions ADD COLUMN IF NOT EXISTS checkin_at DATETIME AFTER liste_attente;
ALTER TABLE inscriptions ADD COLUMN IF NOT EXISTS checkin_par INT AFTER checkin_at;

-- Modifier ENUM statut inscriptions pour ajouter 'liste_attente'
ALTER TABLE inscriptions MODIFY COLUMN statut ENUM('en_attente', 'confirme', 'annule', 'present', 'liste_attente') DEFAULT 'en_attente';

-- Table des dépenses/budget
CREATE TABLE IF NOT EXISTS depenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT,
    categorie ENUM('location', 'materiel', 'nourriture', 'communication', 'artiste', 'transport', 'autre') NOT NULL DEFAULT 'autre',
    description VARCHAR(255) NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_depense DATE NOT NULL,
    fournisseur VARCHAR(255),
    justificatif VARCHAR(255),
    cree_par INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE SET NULL,
    FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_evenement (evenement_id),
    INDEX idx_date (date_depense),
    INDEX idx_categorie (categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des documents membres
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type_document ENUM('identite', 'attestation', 'justificatif', 'autre') NOT NULL DEFAULT 'autre',
    nom_fichier VARCHAR(255) NOT NULL,
    fichier VARCHAR(255) NOT NULL,
    date_expiration DATE,
    notes TEXT,
    uploaded_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_type (type_document)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table log des emails envoyés
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_id INT,
    destinataire_email VARCHAR(255) NOT NULL,
    destinataire_nom VARCHAR(200),
    statut ENUM('envoye', 'erreur', 'en_attente') DEFAULT 'en_attente',
    erreur_message TEXT,
    envoye_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE,
    INDEX idx_email (email_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter paramètres SMTP
INSERT INTO settings (setting_key, setting_value) VALUES
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_email', ''),
('smtp_from_name', '')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Trigger pour générer numéro membre automatiquement
DELIMITER //
CREATE TRIGGER IF NOT EXISTS before_user_insert
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.numero_membre IS NULL AND NEW.role = 'membre' THEN
        SET NEW.numero_membre = CONCAT('M', YEAR(CURDATE()), LPAD((SELECT COALESCE(MAX(CAST(SUBSTRING(numero_membre, 6) AS UNSIGNED)), 0) + 1 FROM users WHERE numero_membre LIKE CONCAT('M', YEAR(CURDATE()), '%')), 4, '0'));
    END IF;
END//
DELIMITER ;

-- Trigger pour générer ticket_code automatiquement
DELIMITER //
CREATE TRIGGER IF NOT EXISTS before_inscription_insert
BEFORE INSERT ON inscriptions
FOR EACH ROW
BEGIN
    IF NEW.ticket_code IS NULL THEN
        SET NEW.ticket_code = UPPER(CONCAT(
            CHAR(65 + FLOOR(RAND() * 26)),
            CHAR(65 + FLOOR(RAND() * 26)),
            LPAD(NEW.evenement_id, 4, '0'),
            '-',
            DATE_FORMAT(NOW(), '%y%m'),
            '-',
            LPAD(FLOOR(RAND() * 10000), 4, '0')
        ));
    END IF;
END//
DELIMITER ;

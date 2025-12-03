-- Mini CRM - Schéma de base de données

-- Table des utilisateurs (avec champs enrichis pour membres)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_membre VARCHAR(20) UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100),
    role ENUM('admin', 'gestionnaire', 'membre') NOT NULL DEFAULT 'membre',
    telephone VARCHAR(20),
    adresse TEXT,
    ville VARCHAR(100),
    code_postal VARCHAR(10),
    pays_origine VARCHAR(100),
    nationalite VARCHAR(100),
    langue_parlee VARCHAR(255),
    date_arrivee DATE,
    date_naissance DATE,
    photo VARCHAR(255),
    carte_generee_le DATE,
    profession VARCHAR(100),
    notes TEXT,
    actif TINYINT(1) DEFAULT 1,
    est_benevole TINYINT(1) DEFAULT 0,
    competences_benevole TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des paramètres
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs de connexion
CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des types de cotisation
CREATE TABLE IF NOT EXISTS cotisation_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    montant DECIMAL(10,2) NOT NULL,
    duree_mois INT DEFAULT 12,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des cotisations des membres
CREATE TABLE IF NOT EXISTS cotisations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cotisation_type_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    date_paiement DATE,
    mode_paiement ENUM('especes', 'cheque', 'carte', 'virement', 'autre') DEFAULT 'especes',
    statut ENUM('en_attente', 'paye', 'annule') DEFAULT 'en_attente',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (cotisation_type_id) REFERENCES cotisation_types(id) ON DELETE RESTRICT,
    INDEX idx_user (user_id),
    INDEX idx_statut (statut),
    INDEX idx_date_fin (date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des événements
CREATE TABLE IF NOT EXISTS evenements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('spectacle', 'soiree', 'tombola', 'atelier', 'reunion', 'autre') NOT NULL DEFAULT 'autre',
    date_debut DATETIME NOT NULL,
    date_fin DATETIME,
    lieu VARCHAR(255),
    adresse TEXT,
    places_max INT DEFAULT 0,
    prix_membre DECIMAL(10,2) DEFAULT 0,
    prix_non_membre DECIMAL(10,2) DEFAULT 0,
    affiche VARCHAR(255),
    statut ENUM('brouillon', 'publie', 'complet', 'annule', 'termine') DEFAULT 'brouillon',
    organisateur_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organisateur_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_date (date_debut),
    INDEX idx_statut (statut),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des inscriptions aux événements
CREATE TABLE IF NOT EXISTS inscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(32) UNIQUE,
    evenement_id INT NOT NULL,
    user_id INT,
    nom_participant VARCHAR(100),
    prenom_participant VARCHAR(100),
    email_participant VARCHAR(255),
    telephone_participant VARCHAR(20),
    nombre_places INT DEFAULT 1,
    montant DECIMAL(10,2) DEFAULT 0,
    statut ENUM('en_attente', 'confirme', 'annule', 'present', 'liste_attente') DEFAULT 'en_attente',
    liste_attente TINYINT(1) DEFAULT 0,
    checkin_at DATETIME,
    checkin_par INT,
    mode_paiement ENUM('especes', 'cheque', 'carte', 'virement', 'gratuit') DEFAULT 'especes',
    date_paiement DATE,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checkin_par) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_evenement (evenement_id),
    INDEX idx_user (user_id),
    INDEX idx_statut (statut),
    INDEX idx_ticket (ticket_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des commentaires/notes sur les membres
CREATE TABLE IF NOT EXISTS membre_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    auteur_id INT NOT NULL,
    contenu TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (auteur_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les emails groupés
CREATE TABLE IF NOT EXISTS emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sujet VARCHAR(255) NOT NULL,
    contenu TEXT NOT NULL,
    destinataires_type ENUM('tous', 'membres', 'gestionnaires', 'cotisation_jour', 'cotisation_retard', 'evenement') DEFAULT 'tous',
    evenement_id INT,
    envoye_par INT,
    date_envoi DATETIME,
    nb_destinataires INT DEFAULT 0,
    statut ENUM('brouillon', 'envoye', 'erreur') DEFAULT 'brouillon',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE SET NULL,
    FOREIGN KEY (envoye_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des bénévoles pour les événements
CREATE TABLE IF NOT EXISTS benevoles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    evenement_id INT NOT NULL,
    role_benevole VARCHAR(100) NOT NULL,
    horaire_debut TIME,
    horaire_fin TIME,
    statut ENUM('propose', 'confirme', 'annule', 'present') DEFAULT 'propose',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_evenement (evenement_id),
    UNIQUE KEY unique_benevole_event (user_id, evenement_id, role_benevole)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

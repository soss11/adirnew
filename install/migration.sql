-- Mini CRM - Migration pour ajouter les nouvelles colonnes
-- Exécuter ce script si vous avez installé avant la mise à jour

-- Ajout des colonnes membres enrichis
ALTER TABLE users
ADD COLUMN IF NOT EXISTS ville VARCHAR(100) AFTER adresse,
ADD COLUMN IF NOT EXISTS code_postal VARCHAR(10) AFTER ville,
ADD COLUMN IF NOT EXISTS pays_origine VARCHAR(100) AFTER code_postal,
ADD COLUMN IF NOT EXISTS nationalite VARCHAR(100) AFTER pays_origine,
ADD COLUMN IF NOT EXISTS langue_parlee VARCHAR(255) AFTER nationalite,
ADD COLUMN IF NOT EXISTS date_arrivee DATE AFTER langue_parlee,
ADD COLUMN IF NOT EXISTS date_naissance DATE AFTER date_arrivee,
ADD COLUMN IF NOT EXISTS photo VARCHAR(255) AFTER date_naissance,
ADD COLUMN IF NOT EXISTS profession VARCHAR(100) AFTER photo,
ADD COLUMN IF NOT EXISTS notes TEXT AFTER profession;

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
    INDEX idx_date (date_debut),
    INDEX idx_statut (statut),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des inscriptions aux événements
CREATE TABLE IF NOT EXISTS inscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT NOT NULL,
    user_id INT,
    nom_participant VARCHAR(100),
    prenom_participant VARCHAR(100),
    email_participant VARCHAR(255),
    telephone_participant VARCHAR(20),
    nombre_places INT DEFAULT 1,
    montant DECIMAL(10,2) DEFAULT 0,
    statut ENUM('en_attente', 'confirme', 'annule', 'present') DEFAULT 'en_attente',
    mode_paiement ENUM('especes', 'cheque', 'carte', 'virement', 'gratuit') DEFAULT 'especes',
    date_paiement DATE,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_evenement (evenement_id),
    INDEX idx_user (user_id),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des commentaires/notes sur les membres
CREATE TABLE IF NOT EXISTS membre_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    auteur_id INT NOT NULL,
    contenu TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
    INDEX idx_user (user_id),
    INDEX idx_evenement (evenement_id),
    UNIQUE KEY unique_benevole_event (user_id, evenement_id, role_benevole)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter colonne bénévole aux utilisateurs
ALTER TABLE users
ADD COLUMN IF NOT EXISTS est_benevole TINYINT(1) DEFAULT 0 AFTER actif,
ADD COLUMN IF NOT EXISTS competences_benevole TEXT AFTER est_benevole;

-- =====================================================
-- Mini CRM - Script de mise à jour V3
-- Exécuter ce script pour ajouter les tables manquantes
-- =====================================================

-- Table des réunions
CREATE TABLE IF NOT EXISTS reunions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_reunion ENUM('ag', 'age', 'ca', 'bureau', 'commission', 'autre') NOT NULL DEFAULT 'autre',
    titre VARCHAR(255) NOT NULL,
    date_reunion DATETIME NOT NULL,
    lieu VARCHAR(255),
    ordre_du_jour TEXT,
    compte_rendu TEXT,
    decisions TEXT,
    fichier_pv VARCHAR(255),
    statut ENUM('planifiee', 'en_cours', 'terminee', 'annulee') DEFAULT 'planifiee',
    quorum_requis INT DEFAULT 0,
    cree_par INT,
    valide_par INT,
    valide_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date_reunion),
    INDEX idx_type (type_reunion),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Participants aux réunions
CREATE TABLE IF NOT EXISTS reunion_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reunion_id INT NOT NULL,
    user_id INT NOT NULL,
    role_reunion ENUM('president', 'secretaire', 'tresorier', 'membre', 'invite') DEFAULT 'membre',
    presence ENUM('present', 'absent', 'excuse', 'procuration') DEFAULT 'present',
    procuration_a INT,
    vote_droit TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reunion_id) REFERENCES reunions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (reunion_id, user_id),
    INDEX idx_reunion (reunion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signatures des PV
CREATE TABLE IF NOT EXISTS reunion_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reunion_id INT NOT NULL,
    user_id INT NOT NULL,
    role_signataire VARCHAR(100),
    signature_data TEXT,
    signe_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (reunion_id) REFERENCES reunions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_signature (reunion_id, user_id),
    INDEX idx_reunion (reunion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Votes en réunion
CREATE TABLE IF NOT EXISTS reunion_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reunion_id INT NOT NULL,
    sujet VARCHAR(255) NOT NULL,
    description TEXT,
    type_vote ENUM('main_levee', 'bulletin', 'unanime') DEFAULT 'main_levee',
    votes_pour INT DEFAULT 0,
    votes_contre INT DEFAULT 0,
    abstentions INT DEFAULT 0,
    resultat ENUM('adopte', 'rejete', 'reporte') DEFAULT NULL,
    ordre INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reunion_id) REFERENCES reunions(id) ON DELETE CASCADE,
    INDEX idx_reunion (reunion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories d'inventaire
CREATE TABLE IF NOT EXISTS inventaire_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    icone VARCHAR(50),
    couleur VARCHAR(7) DEFAULT '#3498db',
    parent_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items d'inventaire
CREATE TABLE IF NOT EXISTS inventaire_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categorie_id INT,
    nom VARCHAR(255) NOT NULL,
    description TEXT,
    code_barre VARCHAR(100),
    reference VARCHAR(100),
    quantite INT DEFAULT 1,
    quantite_min INT DEFAULT 0,
    unite VARCHAR(50) DEFAULT 'pièce',
    emplacement VARCHAR(255),
    valeur_achat DECIMAL(10,2),
    date_achat DATE,
    etat ENUM('neuf', 'bon', 'usage', 'reparer', 'hors_service') DEFAULT 'bon',
    photo VARCHAR(255),
    notes TEXT,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES inventaire_categories(id) ON DELETE SET NULL,
    INDEX idx_categorie (categorie_id),
    INDEX idx_code (code_barre),
    INDEX idx_quantite (quantite, quantite_min)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mouvements d'inventaire
CREATE TABLE IF NOT EXISTS inventaire_mouvements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    type_mouvement ENUM('entree', 'sortie', 'pret', 'retour', 'perte', 'inventaire') NOT NULL,
    quantite INT NOT NULL,
    evenement_id INT,
    user_id INT,
    motif TEXT,
    date_mouvement DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (item_id) REFERENCES inventaire_items(id) ON DELETE CASCADE,
    INDEX idx_item (item_id),
    INDEX idx_date (date_mouvement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réservations d'inventaire
CREATE TABLE IF NOT EXISTS inventaire_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    evenement_id INT,
    quantite INT DEFAULT 1,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    statut ENUM('en_attente', 'confirmee', 'en_cours', 'terminee', 'annulee') DEFAULT 'en_attente',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventaire_items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_item (item_id),
    INDEX idx_dates (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories d'inventaire par défaut
INSERT IGNORE INTO inventaire_categories (nom, description, icone, couleur) VALUES
    ('Matériel événementiel', 'Tables, chaises, barnums, etc.', '🎪', '#e74c3c'),
    ('Sono / Électrique', 'Enceintes, micros, câbles, rallonges', '🔊', '#9b59b6'),
    ('Cuisine', 'Ustensiles, vaisselle, équipements cuisine', '🍽️', '#f39c12'),
    ('Décoration', 'Nappes, guirlandes, affiches', '🎨', '#1abc9c'),
    ('Bureautique', 'Ordinateurs, imprimantes, fournitures', '💻', '#3498db'),
    ('Divers', 'Autres équipements', '📦', '#95a5a6');

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

-- ===========================================
-- NOUVELLES FONCTIONNALITÉS AVANCÉES
-- ===========================================

-- Table des familles (comptes famille)
CREATE TABLE IF NOT EXISTS familles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_famille VARCHAR(100) NOT NULL,
    chef_famille_id INT,
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(255),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chef (chef_famille_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout colonne famille_id et points fidélité dans users
ALTER TABLE users ADD COLUMN IF NOT EXISTS famille_id INT DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS points_fidelite INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS annuaire_visible TINYINT(1) DEFAULT 1;

-- Table des photos d'événements (galerie)
CREATE TABLE IF NOT EXISTS event_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT NOT NULL,
    fichier VARCHAR(255) NOT NULL,
    legende VARCHAR(255),
    ordre INT DEFAULT 0,
    uploaded_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_evenement (evenement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des événements récurrents
CREATE TABLE IF NOT EXISTS event_recurrence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_parent_id INT NOT NULL,
    type_recurrence ENUM('quotidien', 'hebdomadaire', 'mensuel', 'annuel') NOT NULL,
    intervalle INT DEFAULT 1,
    jours_semaine VARCHAR(20),
    date_fin_recurrence DATE,
    nb_occurrences INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_parent_id) REFERENCES evenements(id) ON DELETE CASCADE,
    INDEX idx_parent (evenement_parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout colonne récurrence dans evenements
ALTER TABLE evenements ADD COLUMN IF NOT EXISTS recurrence_id INT DEFAULT NULL;
ALTER TABLE evenements ADD COLUMN IF NOT EXISTS parent_event_id INT DEFAULT NULL;

-- Table des feedbacks/sondages après événements
CREATE TABLE IF NOT EXISTS event_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT NOT NULL,
    user_id INT,
    nom_participant VARCHAR(100),
    email_participant VARCHAR(255),
    note_globale INT CHECK (note_globale BETWEEN 1 AND 5),
    note_organisation INT CHECK (note_organisation BETWEEN 1 AND 5),
    note_lieu INT CHECK (note_lieu BETWEEN 1 AND 5),
    note_animation INT CHECK (note_animation BETWEEN 1 AND 5),
    points_positifs TEXT,
    points_ameliorer TEXT,
    suggestions TEXT,
    recommanderait TINYINT(1),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_evenement (evenement_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des points fidélité (historique)
CREATE TABLE IF NOT EXISTS fidelite_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL,
    type_transaction ENUM('gain', 'utilisation', 'expiration', 'bonus', 'ajustement') NOT NULL,
    description VARCHAR(255),
    reference_type ENUM('cotisation', 'evenement', 'benevole', 'parrainage', 'anniversaire', 'manuel') DEFAULT 'manuel',
    reference_id INT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_type (type_transaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des récompenses fidélité
CREATE TABLE IF NOT EXISTS fidelite_recompenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    points_requis INT NOT NULL,
    type_recompense ENUM('reduction', 'cadeau', 'acces_vip', 'autre') NOT NULL DEFAULT 'autre',
    valeur_reduction DECIMAL(10,2),
    stock INT DEFAULT -1,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des récompenses utilisées
CREATE TABLE IF NOT EXISTS fidelite_utilisations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    recompense_id INT NOT NULL,
    points_utilises INT NOT NULL,
    statut ENUM('en_attente', 'valide', 'annule') DEFAULT 'en_attente',
    notes TEXT,
    valide_par INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recompense_id) REFERENCES fidelite_recompenses(id) ON DELETE RESTRICT,
    FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des réinitialisations de mot de passe
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration des points fidélité
INSERT INTO settings (setting_key, setting_value) VALUES
    ('fidelite_actif', '1'),
    ('fidelite_points_cotisation', '100'),
    ('fidelite_points_evenement', '50'),
    ('fidelite_points_benevole', '200'),
    ('fidelite_points_parrainage', '150'),
    ('fidelite_points_anniversaire', '50')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ===========================================
-- FONCTIONNALITÉS AVANCÉES V2
-- ===========================================

-- Plans de salle pour événements
CREATE TABLE IF NOT EXISTS event_plans_salle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    nb_rangees INT NOT NULL DEFAULT 10,
    places_par_rangee INT NOT NULL DEFAULT 20,
    config JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    INDEX idx_evenement (evenement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Places individuelles
CREATE TABLE IF NOT EXISTS event_places (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    rangee VARCHAR(5) NOT NULL,
    numero INT NOT NULL,
    categorie ENUM('standard', 'vip', 'pmr', 'bloque') DEFAULT 'standard',
    prix_override DECIMAL(10,2),
    inscription_id INT,
    reserve_par INT,
    reserve_at DATETIME,
    FOREIGN KEY (plan_id) REFERENCES event_plans_salle(id) ON DELETE CASCADE,
    FOREIGN KEY (inscription_id) REFERENCES inscriptions(id) ON DELETE SET NULL,
    FOREIGN KEY (reserve_par) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_place (plan_id, rangee, numero),
    INDEX idx_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Covoiturage
CREATE TABLE IF NOT EXISTS covoiturage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT NOT NULL,
    user_id INT NOT NULL,
    type_trajet ENUM('aller', 'retour', 'aller_retour') DEFAULT 'aller_retour',
    role ENUM('conducteur', 'passager') NOT NULL,
    places_disponibles INT DEFAULT 0,
    lieu_depart VARCHAR(255) NOT NULL,
    heure_depart TIME,
    lieu_arrivee VARCHAR(255),
    contribution DECIMAL(10,2) DEFAULT 0,
    commentaire TEXT,
    telephone VARCHAR(20),
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_evenement (evenement_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demandes de covoiturage
CREATE TABLE IF NOT EXISTS covoiturage_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    covoiturage_id INT NOT NULL,
    user_id INT NOT NULL,
    nb_places INT DEFAULT 1,
    message TEXT,
    statut ENUM('en_attente', 'accepte', 'refuse', 'annule') DEFAULT 'en_attente',
    reponse_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (covoiturage_id) REFERENCES covoiturage(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_covoiturage (covoiturage_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sondages et votes
CREATE TABLE IF NOT EXISTS sondages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT,
    type_sondage ENUM('sondage', 'vote', 'election') NOT NULL DEFAULT 'sondage',
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    anonyme TINYINT(1) DEFAULT 0,
    choix_multiple TINYINT(1) DEFAULT 0,
    nb_choix_max INT DEFAULT 1,
    visible_resultat ENUM('toujours', 'apres_vote', 'apres_cloture') DEFAULT 'apres_cloture',
    electeurs ENUM('tous', 'membres', 'admin') DEFAULT 'membres',
    statut ENUM('brouillon', 'actif', 'cloture') DEFAULT 'brouillon',
    cree_par INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_statut (statut),
    INDEX idx_dates (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Options de sondage
CREATE TABLE IF NOT EXISTS sondage_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sondage_id INT NOT NULL,
    texte VARCHAR(255) NOT NULL,
    description TEXT,
    ordre INT DEFAULT 0,
    FOREIGN KEY (sondage_id) REFERENCES sondages(id) ON DELETE CASCADE,
    INDEX idx_sondage (sondage_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Votes des utilisateurs
CREATE TABLE IF NOT EXISTS sondage_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sondage_id INT NOT NULL,
    option_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sondage_id) REFERENCES sondages(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES sondage_options(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sondage (sondage_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration des rappels automatiques
CREATE TABLE IF NOT EXISTS rappels_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_rappel ENUM('cotisation_expiration', 'cotisation_retard', 'evenement', 'anniversaire', 'bienvenue') NOT NULL,
    delai_jours INT NOT NULL DEFAULT 7,
    actif TINYINT(1) DEFAULT 1,
    sujet VARCHAR(255),
    template TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_type (type_rappel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logs des rappels envoyés
CREATE TABLE IF NOT EXISTS rappels_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_rappel VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    envoye_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('envoye', 'erreur') DEFAULT 'envoye',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_type (type_rappel),
    INDEX idx_user (user_id),
    INDEX idx_date (envoye_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clés API pour REST
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    permissions JSON,
    derniere_utilisation DATETIME,
    nb_requetes INT DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    expire_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_key (api_key),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logs API
CREATE TABLE IF NOT EXISTS api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT,
    endpoint VARCHAR(255),
    method VARCHAR(10),
    ip_address VARCHAR(45),
    response_code INT,
    execution_time FLOAT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE SET NULL,
    INDEX idx_key (api_key_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration par défaut des rappels
INSERT INTO rappels_config (type_rappel, delai_jours, actif, sujet, template) VALUES
    ('cotisation_expiration', 30, 1, 'Votre cotisation expire bientôt', 'Bonjour {prenom},\n\nVotre cotisation expire le {date_fin}. Pensez à la renouveler !\n\nCordialement,\n{asso_nom}'),
    ('cotisation_retard', 7, 1, 'Cotisation en retard', 'Bonjour {prenom},\n\nVotre cotisation a expiré depuis le {date_fin}. Merci de la renouveler.\n\nCordialement,\n{asso_nom}'),
    ('evenement', 2, 1, 'Rappel : {event_titre}', 'Bonjour {prenom},\n\nN''oubliez pas l''événement "{event_titre}" qui aura lieu le {event_date} à {event_lieu}.\n\nÀ bientôt !'),
    ('anniversaire', 0, 1, 'Joyeux anniversaire !', 'Cher(e) {prenom},\n\nToute l''équipe de {asso_nom} vous souhaite un très joyeux anniversaire !\n\nBelle journée !'),
    ('bienvenue', 0, 1, 'Bienvenue chez {asso_nom}', 'Bonjour {prenom},\n\nBienvenue parmi nous ! Votre numéro de membre est : {numero_membre}.\n\nCordialement,\n{asso_nom}')
ON DUPLICATE KEY UPDATE type_rappel = type_rappel;

-- ===========================================
-- FONCTIONNALITÉS AVANCÉES V3
-- ===========================================

-- Réunions et comptes-rendus (AG, CA, etc.)
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
    FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL,
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
    FOREIGN KEY (procuration_a) REFERENCES users(id) ON DELETE SET NULL,
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
    FOREIGN KEY (parent_id) REFERENCES inventaire_categories(id) ON DELETE SET NULL,
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
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
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
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE SET NULL,
    INDEX idx_item (item_id),
    INDEX idx_dates (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comptes réseaux sociaux
CREATE TABLE IF NOT EXISTS social_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plateforme ENUM('facebook', 'instagram', 'twitter', 'linkedin') NOT NULL,
    nom_compte VARCHAR(255),
    page_id VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    actif TINYINT(1) DEFAULT 1,
    connected_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (connected_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_plateforme (plateforme)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Publications réseaux sociaux
CREATE TABLE IF NOT EXISTS social_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evenement_id INT,
    plateformes JSON,
    type_post ENUM('annonce', 'rappel', 'compte_rendu', 'photo', 'custom') DEFAULT 'annonce',
    contenu TEXT NOT NULL,
    image VARCHAR(255),
    lien VARCHAR(500),
    date_publication DATETIME,
    publie_immediatement TINYINT(1) DEFAULT 0,
    statut ENUM('brouillon', 'planifie', 'publie', 'erreur') DEFAULT 'brouillon',
    resultats JSON,
    cree_par INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE SET NULL,
    FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_evenement (evenement_id),
    INDEX idx_statut (statut),
    INDEX idx_date (date_publication)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logs publications
CREATE TABLE IF NOT EXISTS social_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    plateforme VARCHAR(50) NOT NULL,
    post_externe_id VARCHAR(255),
    statut ENUM('succes', 'erreur') NOT NULL,
    message TEXT,
    likes INT DEFAULT 0,
    partages INT DEFAULT 0,
    commentaires INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES social_posts(id) ON DELETE CASCADE,
    INDEX idx_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories d'inventaire par défaut
INSERT INTO inventaire_categories (nom, description, icone, couleur) VALUES
    ('Matériel événementiel', 'Tables, chaises, barnums, etc.', '🎪', '#e74c3c'),
    ('Sono / Électrique', 'Enceintes, micros, câbles, rallonges', '🔊', '#9b59b6'),
    ('Cuisine', 'Ustensiles, vaisselle, équipements cuisine', '🍽️', '#f39c12'),
    ('Décoration', 'Nappes, guirlandes, affiches', '🎨', '#1abc9c'),
    ('Bureautique', 'Ordinateurs, imprimantes, fournitures', '💻', '#3498db'),
    ('Divers', 'Autres équipements', '📦', '#95a5a6')
ON DUPLICATE KEY UPDATE nom = nom;

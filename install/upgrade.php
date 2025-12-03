<?php
/**
 * Mini CRM - Script de mise à jour de la base de données
 * Exécute les nouvelles tables et colonnes pour les fonctionnalités avancées
 */

require_once __DIR__ . '/../includes/db.php';

$messages = [];
$errors = [];

// Fonction pour exécuter une requête et capturer les erreurs
function runQuery($pdo, $sql, $description) {
    global $messages, $errors;
    try {
        $pdo->exec($sql);
        $messages[] = "✓ $description";
        return true;
    } catch (PDOException $e) {
        // Ignorer les erreurs "already exists"
        if (strpos($e->getMessage(), 'already exists') !== false ||
            strpos($e->getMessage(), 'Duplicate') !== false) {
            $messages[] = "○ $description (déjà existant)";
            return true;
        }
        $errors[] = "✗ $description: " . $e->getMessage();
        return false;
    }
}

// Table des familles
runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table familles");

// Colonnes users
runQuery($pdo, "ALTER TABLE users ADD COLUMN famille_id INT DEFAULT NULL", "Colonne famille_id dans users");
runQuery($pdo, "ALTER TABLE users ADD COLUMN points_fidelite INT DEFAULT 0", "Colonne points_fidelite dans users");
runQuery($pdo, "ALTER TABLE users ADD COLUMN annuaire_visible TINYINT(1) DEFAULT 1", "Colonne annuaire_visible dans users");

// Table photos événements
runQuery($pdo, "
    CREATE TABLE IF NOT EXISTS event_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evenement_id INT NOT NULL,
        fichier VARCHAR(255) NOT NULL,
        legende VARCHAR(255),
        ordre INT DEFAULT 0,
        uploaded_by INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
        INDEX idx_evenement (evenement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table event_photos");

// Table récurrence événements
runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table event_recurrence");

// Colonnes événements
runQuery($pdo, "ALTER TABLE evenements ADD COLUMN recurrence_id INT DEFAULT NULL", "Colonne recurrence_id dans evenements");
runQuery($pdo, "ALTER TABLE evenements ADD COLUMN parent_event_id INT DEFAULT NULL", "Colonne parent_event_id dans evenements");

// Table feedback
runQuery($pdo, "
    CREATE TABLE IF NOT EXISTS event_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        evenement_id INT NOT NULL,
        user_id INT,
        nom_participant VARCHAR(100),
        email_participant VARCHAR(255),
        note_globale INT,
        note_organisation INT,
        note_lieu INT,
        note_animation INT,
        points_positifs TEXT,
        points_ameliorer TEXT,
        suggestions TEXT,
        recommanderait TINYINT(1),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (evenement_id) REFERENCES evenements(id) ON DELETE CASCADE,
        INDEX idx_evenement (evenement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table event_feedback");

// Table transactions fidélité
runQuery($pdo, "
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
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table fidelite_transactions");

// Table récompenses
runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table fidelite_recompenses");

// Table utilisations récompenses
runQuery($pdo, "
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
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table fidelite_utilisations");

// Table reset mot de passe
runQuery($pdo, "
    CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table password_resets");

// Paramètres fidélité
$settings = [
    ['fidelite_actif', '1'],
    ['fidelite_points_cotisation', '100'],
    ['fidelite_points_evenement', '50'],
    ['fidelite_points_benevole', '200'],
    ['fidelite_points_parrainage', '150'],
    ['fidelite_points_anniversaire', '50']
];

foreach ($settings as $s) {
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_key = setting_key");
        $stmt->execute($s);
    } catch (PDOException $e) {}
}
$messages[] = "✓ Paramètres fidélité configurés";

// ===========================================
// FONCTIONNALITÉS AVANCÉES V2
// ===========================================

// Plans de salle
runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table event_plans_salle");

runQuery($pdo, "
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
        UNIQUE KEY unique_place (plan_id, rangee, numero),
        INDEX idx_plan (plan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table event_places");

// Covoiturage
runQuery($pdo, "
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
        INDEX idx_evenement (evenement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table covoiturage");

runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table covoiturage_reservations");

// Sondages
runQuery($pdo, "
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
        INDEX idx_statut (statut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table sondages");

runQuery($pdo, "
    CREATE TABLE IF NOT EXISTS sondage_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sondage_id INT NOT NULL,
        texte VARCHAR(255) NOT NULL,
        description TEXT,
        ordre INT DEFAULT 0,
        FOREIGN KEY (sondage_id) REFERENCES sondages(id) ON DELETE CASCADE,
        INDEX idx_sondage (sondage_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table sondage_options");

runQuery($pdo, "
    CREATE TABLE IF NOT EXISTS sondage_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sondage_id INT NOT NULL,
        option_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sondage_id) REFERENCES sondages(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES sondage_options(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_sondage (sondage_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table sondage_votes");

// Rappels
runQuery($pdo, "
    CREATE TABLE IF NOT EXISTS rappels_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type_rappel ENUM('cotisation_expiration', 'cotisation_retard', 'evenement', 'anniversaire', 'bienvenue') NOT NULL,
        delai_jours INT NOT NULL DEFAULT 7,
        actif TINYINT(1) DEFAULT 1,
        sujet VARCHAR(255),
        template TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_type (type_rappel)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table rappels_config");

runQuery($pdo, "
    CREATE TABLE IF NOT EXISTS rappels_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type_rappel VARCHAR(50) NOT NULL,
        user_id INT NOT NULL,
        reference_type VARCHAR(50),
        reference_id INT,
        envoye_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        statut ENUM('envoye', 'erreur') DEFAULT 'envoye',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_type (type_rappel)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table rappels_logs");

// API
runQuery($pdo, "
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
        INDEX idx_key (api_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table api_keys");

runQuery($pdo, "
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
        INDEX idx_date (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table api_logs");

// Rappels par défaut
$rappels = [
    ['cotisation_expiration', 30, 1, 'Votre cotisation expire bientôt', 'Bonjour {prenom},\n\nVotre cotisation expire le {date_fin}. Pensez à la renouveler !\n\nCordialement,\n{asso_nom}'],
    ['cotisation_retard', 7, 1, 'Cotisation en retard', 'Bonjour {prenom},\n\nVotre cotisation a expiré depuis le {date_fin}. Merci de la renouveler.\n\nCordialement,\n{asso_nom}'],
    ['evenement', 2, 1, 'Rappel : {event_titre}', 'Bonjour {prenom},\n\nN\'oubliez pas l\'événement "{event_titre}" qui aura lieu le {event_date} à {event_lieu}.\n\nÀ bientôt !'],
    ['anniversaire', 0, 1, 'Joyeux anniversaire !', 'Cher(e) {prenom},\n\nToute l\'équipe de {asso_nom} vous souhaite un très joyeux anniversaire !\n\nBelle journée !'],
    ['bienvenue', 0, 1, 'Bienvenue chez {asso_nom}', 'Bonjour {prenom},\n\nBienvenue parmi nous ! Votre numéro de membre est : {numero_membre}.\n\nCordialement,\n{asso_nom}']
];
foreach ($rappels as $r) {
    try {
        $stmt = $pdo->prepare("INSERT INTO rappels_config (type_rappel, delai_jours, actif, sujet, template) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE type_rappel = type_rappel");
        $stmt->execute($r);
    } catch (PDOException $e) {}
}
$messages[] = "✓ Configuration des rappels";

// ===========================================
// FONCTIONNALITÉS AVANCÉES V3
// ===========================================

// Réunions
runQuery($pdo, "
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
        INDEX idx_date (date_reunion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table reunions");

runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table reunion_participants");

runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table reunion_signatures");

runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table reunion_votes");

// Inventaire
runQuery($pdo, "
    CREATE TABLE IF NOT EXISTS inventaire_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        description TEXT,
        icone VARCHAR(50),
        couleur VARCHAR(7) DEFAULT '#3498db',
        parent_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_parent (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table inventaire_categories");

runQuery($pdo, "
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
        INDEX idx_categorie (categorie_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table inventaire_items");

runQuery($pdo, "
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
        INDEX idx_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table inventaire_mouvements");

runQuery($pdo, "
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
        INDEX idx_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table inventaire_reservations");

// Catégories inventaire par défaut
$cats = [
    ['Matériel événementiel', 'Tables, chaises, barnums, etc.', '🎪', '#e74c3c'],
    ['Sono / Électrique', 'Enceintes, micros, câbles, rallonges', '🔊', '#9b59b6'],
    ['Cuisine', 'Ustensiles, vaisselle, équipements cuisine', '🍽️', '#f39c12'],
    ['Décoration', 'Nappes, guirlandes, affiches', '🎨', '#1abc9c'],
    ['Bureautique', 'Ordinateurs, imprimantes, fournitures', '💻', '#3498db'],
    ['Divers', 'Autres équipements', '📦', '#95a5a6']
];
foreach ($cats as $c) {
    try {
        $stmt = $pdo->prepare("INSERT INTO inventaire_categories (nom, description, icone, couleur) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nom = nom");
        $stmt->execute($c);
    } catch (PDOException $e) {}
}
$messages[] = "✓ Catégories inventaire par défaut";

// Réseaux sociaux
runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table social_accounts");

runQuery($pdo, "
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
        INDEX idx_statut (statut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table social_posts");

runQuery($pdo, "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
", "Table social_logs");

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour - Mini CRM</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f5f5; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a56db; margin-bottom: 20px; }
        .message { padding: 8px 0; border-bottom: 1px solid #eee; }
        .message:last-child { border-bottom: none; }
        .error { color: #dc2626; }
        .success { color: #16a34a; }
        .neutral { color: #666; }
        .btn { display: inline-block; padding: 12px 24px; background: #1a56db; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .btn:hover { background: #1e40af; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mise à jour de la base de données</h1>

        <?php foreach ($messages as $msg): ?>
            <div class="message <?php echo strpos($msg, '✓') === 0 ? 'success' : 'neutral'; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($errors as $err): ?>
            <div class="message error"><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>

        <?php if (empty($errors)): ?>
            <p style="margin-top: 20px; color: #16a34a; font-weight: bold;">
                Mise à jour terminée avec succès !
            </p>
        <?php else: ?>
            <p style="margin-top: 20px; color: #dc2626;">
                Certaines erreurs se sont produites. Vérifiez les messages ci-dessus.
            </p>
        <?php endif; ?>

        <a href="../dashboard.php" class="btn">Retour au tableau de bord</a>
    </div>
</body>
</html>

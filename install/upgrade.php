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

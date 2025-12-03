<?php
/**
 * Mini CRM - Assistant d'installation
 * Installation facile comme WordPress
 */

session_start();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Vérifier si déjà installé
if (file_exists('../config/config.php') && $step < 4) {
    header('Location: ../index.php');
    exit;
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Étape 2: Configuration BDD
    if ($step === 2) {
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';

        // Tester la connexion
        try {
            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Créer la base de données si elle n'existe pas
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");

            // Créer les tables
            $sql = file_get_contents('schema.sql');
            $pdo->exec($sql);

            // Sauvegarder la configuration
            $_SESSION['install'] = [
                'db_host' => $db_host,
                'db_name' => $db_name,
                'db_user' => $db_user,
                'db_pass' => $db_pass
            ];

            header('Location: ?step=3');
            exit;

        } catch (PDOException $e) {
            $error = "Erreur de connexion: " . $e->getMessage();
        }
    }

    // Étape 3: Création compte admin + infos association
    if ($step === 3 && isset($_SESSION['install'])) {
        $admin_email = trim($_POST['admin_email'] ?? '');
        $admin_pass = $_POST['admin_pass'] ?? '';
        $admin_pass2 = $_POST['admin_pass2'] ?? '';
        $admin_nom = trim($_POST['admin_nom'] ?? '');
        $admin_prenom = trim($_POST['admin_prenom'] ?? '');

        $asso_nom = trim($_POST['asso_nom'] ?? '');
        $asso_adresse = trim($_POST['asso_adresse'] ?? '');
        $asso_tel = trim($_POST['asso_tel'] ?? '');
        $asso_email = trim($_POST['asso_email'] ?? '');

        if (empty($admin_email) || empty($admin_pass) || empty($admin_nom)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif ($admin_pass !== $admin_pass2) {
            $error = "Les mots de passe ne correspondent pas.";
        } elseif (strlen($admin_pass) < 6) {
            $error = "Le mot de passe doit contenir au moins 6 caractères.";
        } else {
            try {
                $cfg = $_SESSION['install'];
                $pdo = new PDO(
                    "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4",
                    $cfg['db_user'],
                    $cfg['db_pass']
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Créer le compte admin
                $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, nom, prenom, role, actif, created_at) VALUES (?, ?, ?, ?, 'admin', 1, NOW())");
                $stmt->execute([$admin_email, $hash, $admin_nom, $admin_prenom]);

                // Sauvegarder les infos de l'association
                $settings = [
                    'asso_nom' => $asso_nom,
                    'asso_adresse' => $asso_adresse,
                    'asso_tel' => $asso_tel,
                    'asso_email' => $asso_email,
                    'asso_logo' => ''
                ];

                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }

                // Créer le fichier de configuration
                $config_content = "<?php
/**
 * Configuration Mini CRM
 * Généré automatiquement lors de l'installation
 */

define('DB_HOST', '{$cfg['db_host']}');
define('DB_NAME', '{$cfg['db_name']}');
define('DB_USER', '{$cfg['db_user']}');
define('DB_PASS', '{$cfg['db_pass']}');

define('SITE_URL', rtrim(dirname((isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . \$_SERVER['HTTP_HOST'] . \$_SERVER['PHP_SELF']), '/'));
define('INSTALLED', true);
";

                file_put_contents('../config/config.php', $config_content);

                // Nettoyer la session d'installation
                unset($_SESSION['install']);

                header('Location: ?step=4');
                exit;

            } catch (PDOException $e) {
                $error = "Erreur: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Mini CRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 600px;
            padding: 40px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #667eea;
            font-size: 28px;
        }
        .logo p {
            color: #666;
            margin-top: 5px;
        }
        .steps {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step-item {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 10px;
            position: relative;
        }
        .step-item.active {
            background: #667eea;
            color: white;
        }
        .step-item.done {
            background: #4caf50;
            color: white;
        }
        .step-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 45px;
            top: 50%;
            width: 20px;
            height: 2px;
            background: #e0e0e0;
        }
        .step-item.done:not(:last-child)::after {
            background: #4caf50;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5a6fd6;
        }
        .btn-success {
            background: #4caf50;
        }
        .btn-success:hover {
            background: #43a047;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        .section-title {
            font-size: 14px;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 25px 0 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e0e0;
        }
        .success-icon {
            font-size: 60px;
            text-align: center;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box p {
            margin: 5px 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="logo">
            <h1>Mini CRM</h1>
            <p>Assistant d'installation</p>
        </div>

        <div class="steps">
            <div class="step-item <?php echo $step >= 1 ? ($step > 1 ? 'done' : 'active') : ''; ?>">1</div>
            <div class="step-item <?php echo $step >= 2 ? ($step > 2 ? 'done' : 'active') : ''; ?>">2</div>
            <div class="step-item <?php echo $step >= 3 ? ($step > 3 ? 'done' : 'active') : ''; ?>">3</div>
            <div class="step-item <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- Étape 1: Bienvenue -->
            <h2>Bienvenue !</h2>
            <div class="info-box">
                <p>Ce mini CRM va vous aider à gérer votre association facilement.</p>
                <p><strong>Fonctionnalités :</strong></p>
                <p>- Gestion des membres</p>
                <p>- 3 niveaux d'accès : Admin, Gestionnaire, Membre</p>
                <p>- Personnalisation (logo, informations)</p>
            </div>
            <p style="margin-bottom: 20px; color: #666;">Avant de commencer, assurez-vous d'avoir :</p>
            <ul style="margin-bottom: 20px; margin-left: 20px; color: #666;">
                <li>Les informations de connexion à votre base de données MySQL</li>
                <li>Un nom pour votre association</li>
            </ul>
            <a href="?step=2" class="btn" style="display: block; text-align: center; text-decoration: none;">Commencer l'installation</a>

        <?php elseif ($step === 2): ?>
            <!-- Étape 2: Configuration BDD -->
            <h2>Configuration de la base de données</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Serveur MySQL</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Nom de la base de données</label>
                    <input type="text" name="db_name" placeholder="minicrm" required>
                </div>
                <div class="form-group">
                    <label>Utilisateur MySQL</label>
                    <input type="text" name="db_user" placeholder="root" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe MySQL</label>
                    <input type="password" name="db_pass">
                </div>
                <button type="submit" class="btn">Tester et continuer</button>
            </form>

        <?php elseif ($step === 3): ?>
            <!-- Étape 3: Compte admin + Infos association -->
            <h2>Configuration finale</h2>
            <form method="POST">
                <div class="section-title">Compte Administrateur</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="admin_nom" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="admin_prenom">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="admin_email" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Mot de passe *</label>
                        <input type="password" name="admin_pass" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer *</label>
                        <input type="password" name="admin_pass2" required>
                    </div>
                </div>

                <div class="section-title">Informations de l'association</div>
                <div class="form-group">
                    <label>Nom de l'association</label>
                    <input type="text" name="asso_nom" placeholder="Mon Association">
                </div>
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" name="asso_adresse">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="asso_tel">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="asso_email">
                    </div>
                </div>

                <button type="submit" class="btn">Terminer l'installation</button>
            </form>

        <?php elseif ($step === 4): ?>
            <!-- Étape 4: Terminé -->
            <div class="success-icon">&#10004;</div>
            <h2>Installation terminée !</h2>
            <div class="alert alert-success">
                Votre Mini CRM a été installé avec succès.
            </div>
            <div class="info-box">
                <p><strong>Prochaines étapes :</strong></p>
                <p>1. Connectez-vous avec votre compte administrateur</p>
                <p>2. Personnalisez les paramètres (logo, etc.)</p>
                <p>3. Créez des comptes pour vos gestionnaires et membres</p>
            </div>
            <a href="../index.php" class="btn btn-success" style="display: block; text-align: center; text-decoration: none;">Accéder au CRM</a>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
/**
 * Mini CRM - Page de connexion
 */

session_start();

// Vérifier si installé
if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: install/index.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $result = login($email, $password);
        if ($result['success']) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Récupérer les infos de l'association
$asso = getAssociationSettings();

// Message d'erreur depuis l'URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'login_required':
            $error = 'Veuillez vous connecter pour accéder à cette page.';
            break;
        case 'session_expired':
            $error = 'Votre session a expiré. Veuillez vous reconnecter.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo e($asso['nom'] ?: 'Mini CRM'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <?php if (!empty($asso['logo'])): ?>
                    <img src="assets/uploads/<?php echo e($asso['logo']); ?>" alt="Logo" class="logo">
                <?php else: ?>
                    <div class="logo-placeholder"><?php echo e(substr($asso['nom'] ?: 'MC', 0, 2)); ?></div>
                <?php endif; ?>
                <h1><?php echo e($asso['nom'] ?: 'Mini CRM'); ?></h1>
                <p>Connectez-vous à votre espace</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Se connecter</button>
            </form>
        </div>
    </div>
</body>
</html>

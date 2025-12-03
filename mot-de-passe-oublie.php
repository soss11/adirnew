<?php
/**
 * Mini CRM - Mot de passe oublié
 */

session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Veuillez entrer votre adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        // Vérifier si l'utilisateur existe
        $stmt = $pdo->prepare("SELECT id, nom, prenom FROM users WHERE email = ? AND actif = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Générer un token unique
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Invalider les anciens tokens
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?");
            $stmt->execute([$user['id']]);

            // Créer le nouveau token
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user['id'], $token, $expires]);

            // Construire le lien de réinitialisation
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = dirname($_SERVER['SCRIPT_NAME']);
            $resetLink = "$protocol://$host$path/reset-password.php?token=$token";

            // Envoyer l'email
            $asso = getAssociationSettings();
            $to = $email;
            $subject = "Réinitialisation de votre mot de passe - " . ($asso['nom'] ?: 'Mini CRM');

            $message = "Bonjour " . $user['prenom'] . ",\n\n";
            $message .= "Vous avez demandé la réinitialisation de votre mot de passe.\n\n";
            $message .= "Cliquez sur le lien suivant pour créer un nouveau mot de passe :\n";
            $message .= $resetLink . "\n\n";
            $message .= "Ce lien est valable pendant 1 heure.\n\n";
            $message .= "Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.\n\n";
            $message .= "Cordialement,\n";
            $message .= $asso['nom'] ?: 'Mini CRM';

            $headers = "From: " . ($asso['email'] ?: 'noreply@example.com') . "\r\n";
            $headers .= "Reply-To: " . ($asso['email'] ?: 'noreply@example.com') . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            // Essayer d'envoyer l'email
            @mail($to, $subject, $message, $headers);
        }

        // Toujours afficher le même message (sécurité)
        $success = true;
    }
}

$asso = getAssociationSettings();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - <?php echo htmlspecialchars($asso['nom'] ?: 'Mini CRM'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-box { max-width: 400px; }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--primary);
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        .success-message {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .success-message .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <?php if (!empty($asso['logo'])): ?>
                    <img src="assets/uploads/<?php echo htmlspecialchars($asso['logo']); ?>" alt="Logo" class="logo">
                <?php else: ?>
                    <div class="logo-placeholder"><?php echo htmlspecialchars(substr($asso['nom'] ?: 'MC', 0, 2)); ?></div>
                <?php endif; ?>
                <h1>Mot de passe oublié</h1>
                <p>Entrez votre email pour recevoir un lien de réinitialisation</p>
            </div>

            <?php if ($success): ?>
                <div class="success-message">
                    <div class="icon">&#9993;</div>
                    <h3>Email envoyé !</h3>
                    <p>Si cette adresse email est associée à un compte, vous recevrez un lien de réinitialisation dans quelques minutes.</p>
                    <p style="margin-top: 15px; font-size: 14px; color: #666;">Pensez à vérifier vos spams.</p>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="email">Adresse email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Envoyer le lien</button>
                </form>
            <?php endif; ?>

            <a href="index.php" class="back-link">&larr; Retour à la connexion</a>
        </div>
    </div>
</body>
</html>

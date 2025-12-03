<?php
/**
 * Mini CRM - Réinitialisation du mot de passe
 */

session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';
$success = false;
$error = '';
$validToken = false;
$user = null;

// Vérifier le token
if ($token) {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.id as user_id, u.nom, u.prenom, u.email
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW() AND u.actif = 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if ($reset) {
        $validToken = true;
        $user = $reset;
    }
}

// Traiter le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        // Mettre à jour le mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['user_id']]);

        // Marquer le token comme utilisé
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);

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
    <title>Nouveau mot de passe - <?php echo htmlspecialchars($asso['nom'] ?: 'Mini CRM'); ?></title>
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
        .success-message, .error-message {
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }
        .success-message {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        .message-icon { font-size: 48px; margin-bottom: 15px; }
        .password-strength {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        .password-strength .bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }
        .password-strength .bar.weak { width: 33%; background: #ef4444; }
        .password-strength .bar.medium { width: 66%; background: #f59e0b; }
        .password-strength .bar.strong { width: 100%; background: #10b981; }
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
                <h1>Nouveau mot de passe</h1>
            </div>

            <?php if ($success): ?>
                <div class="success-message">
                    <div class="message-icon">&#10004;</div>
                    <h3>Mot de passe modifié !</h3>
                    <p>Votre mot de passe a été mis à jour avec succès.</p>
                    <a href="index.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block;">Se connecter</a>
                </div>

            <?php elseif (!$validToken): ?>
                <div class="error-message">
                    <div class="message-icon">&#10060;</div>
                    <h3>Lien invalide ou expiré</h3>
                    <p>Ce lien de réinitialisation n'est plus valide. Il a peut-être expiré ou a déjà été utilisé.</p>
                    <a href="mot-de-passe-oublie.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block;">Nouvelle demande</a>
                </div>

            <?php else: ?>
                <p style="text-align: center; margin-bottom: 20px; color: #666;">
                    Bonjour <?php echo htmlspecialchars($user['prenom']); ?>, créez votre nouveau mot de passe.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="password">Nouveau mot de passe</label>
                        <input type="password" id="password" name="password" required minlength="6" oninput="checkStrength(this.value)">
                        <div class="password-strength">
                            <div class="bar" id="strengthBar"></div>
                        </div>
                        <p class="form-hint">Au moins 6 caractères</p>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Enregistrer</button>
                </form>
            <?php endif; ?>

            <a href="index.php" class="back-link">&larr; Retour à la connexion</a>
        </div>
    </div>

    <script>
    function checkStrength(password) {
        var bar = document.getElementById('strengthBar');
        bar.className = 'bar';

        if (password.length === 0) {
            bar.style.width = '0';
            return;
        }

        var strength = 0;
        if (password.length >= 6) strength++;
        if (password.length >= 10) strength++;
        if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        if (strength <= 2) {
            bar.className = 'bar weak';
        } else if (strength <= 3) {
            bar.className = 'bar medium';
        } else {
            bar.className = 'bar strong';
        }
    }
    </script>
</body>
</html>

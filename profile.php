<?php
/**
 * Mini CRM - Profil utilisateur
 */

$pageTitle = 'Mon profil';
require_once __DIR__ . '/includes/header.php';

requireLogin();

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($nom)) {
        $error = 'Le nom est obligatoire.';
    } elseif (!empty($password) && $password !== $password2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET nom = ?, prenom = ?, telephone = ?, adresse = ?, password = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $telephone, $adresse, $hash, $currentUser['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nom = ?, prenom = ?, telephone = ?, adresse = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $telephone, $adresse, $currentUser['id']]);
            }

            setFlashMessage('success', 'Votre profil a été mis à jour.');
            header('Location: profile.php');
            exit;

        } catch (PDOException $e) {
            $error = 'Erreur lors de la mise à jour du profil.';
        }
    }
}

// Rafraîchir les données utilisateur
$currentUser = getCurrentUser();
?>

<div class="card">
    <div class="card-header">
        <h3>Modifier mon profil</h3>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="section-title">Informations personnelles</div>

            <div class="form-row">
                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" value="<?php echo e($currentUser['nom']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" value="<?php echo e($currentUser['prenom'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" value="<?php echo e($currentUser['email']); ?>" disabled>
                <p class="form-hint">L'email ne peut pas être modifié.</p>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="text" id="telephone" name="telephone" value="<?php echo e($currentUser['telephone'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="adresse">Adresse</label>
                <textarea id="adresse" name="adresse"><?php echo e($currentUser['adresse'] ?? ''); ?></textarea>
            </div>

            <div class="section-title">Changer le mot de passe</div>
            <p style="color: #666; margin-bottom: 15px;">Laissez vide pour conserver le mot de passe actuel.</p>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <input type="password" id="password" name="password">
                </div>
                <div class="form-group">
                    <label for="password2">Confirmer</label>
                    <input type="password" id="password2" name="password2">
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Informations du compte</h3>
    </div>
    <div class="card-body">
        <table>
            <tr>
                <td style="width: 200px; font-weight: 500;">Rôle</td>
                <td><span class="badge badge-<?php echo $currentUser['role']; ?>"><?php echo translateRole($currentUser['role']); ?></span></td>
            </tr>
            <tr>
                <td style="font-weight: 500;">Compte créé le</td>
                <td><?php echo formatDateTime($currentUser['created_at']); ?></td>
            </tr>
            <tr>
                <td style="font-weight: 500;">Dernière connexion</td>
                <td><?php echo $currentUser['last_login'] ? formatDateTime($currentUser['last_login']) : '-'; ?></td>
            </tr>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

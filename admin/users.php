<?php
/**
 * Mini CRM - Gestion des utilisateurs
 */

$pageTitle = 'Utilisateurs';
require_once __DIR__ . '/../includes/header.php';

requireRole('gestionnaire');

$error = '';
$success = '';

// Actions
$action = $_GET['action'] ?? '';
$userId = (int)($_GET['id'] ?? 0);

// Supprimer un utilisateur
if ($action === 'delete' && $userId > 0) {
    if ($userId === $currentUser['id']) {
        setFlashMessage('error', 'Vous ne pouvez pas supprimer votre propre compte.');
    } else {
        // Vérifier les droits
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            // Un gestionnaire ne peut pas supprimer un admin
            if ($currentUser['role'] !== 'admin' && $targetUser['role'] === 'admin') {
                setFlashMessage('error', 'Vous n\'avez pas les droits pour supprimer cet utilisateur.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                setFlashMessage('success', 'Utilisateur supprimé avec succès.');
            }
        }
    }
    header('Location: users.php');
    exit;
}

// Activer/Désactiver un utilisateur
if ($action === 'toggle' && $userId > 0) {
    if ($userId === $currentUser['id']) {
        setFlashMessage('error', 'Vous ne pouvez pas désactiver votre propre compte.');
    } else {
        $stmt = $pdo->prepare("UPDATE users SET actif = NOT actif WHERE id = ?");
        $stmt->execute([$userId]);
        setFlashMessage('success', 'Statut de l\'utilisateur modifié.');
    }
    header('Location: users.php');
    exit;
}

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $role = $_POST['role'] ?? 'membre';
    $password = $_POST['password'] ?? '';
    $telephone = trim($_POST['telephone'] ?? '');

    // Validation
    if (empty($email) || empty($nom)) {
        $error = 'L\'email et le nom sont obligatoires.';
    } elseif (!isValidEmail($email)) {
        $error = 'Adresse email invalide.';
    } elseif (!in_array($role, ['admin', 'gestionnaire', 'membre'])) {
        $error = 'Rôle invalide.';
    } elseif ($editId === 0 && empty($password)) {
        $error = 'Le mot de passe est obligatoire pour un nouvel utilisateur.';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $editId]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } else {
            // Un gestionnaire ne peut pas créer d'admin
            if ($currentUser['role'] !== 'admin' && $role === 'admin') {
                $error = 'Vous n\'avez pas les droits pour créer un administrateur.';
            }
        }
    }

    if (empty($error)) {
        try {
            if ($editId > 0) {
                // Modification
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, nom = ?, prenom = ?, role = ?, telephone = ?, password = ? WHERE id = ?");
                    $stmt->execute([$email, $nom, $prenom, $role, $telephone, $hash, $editId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, nom = ?, prenom = ?, role = ?, telephone = ? WHERE id = ?");
                    $stmt->execute([$email, $nom, $prenom, $role, $telephone, $editId]);
                }
                setFlashMessage('success', 'Utilisateur modifié avec succès.');
            } else {
                // Création
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, nom, prenom, role, telephone, actif, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$email, $hash, $nom, $prenom, $role, $telephone]);
                setFlashMessage('success', 'Utilisateur créé avec succès.');
            }
            header('Location: users.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'enregistrement.';
        }
    }
}

// Récupérer l'utilisateur à éditer
$editUser = null;
if ($action === 'edit' && $userId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $editUser = $stmt->fetch();
}

// Liste des utilisateurs
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<!-- Modal Ajouter/Modifier -->
<div class="modal-overlay <?php echo ($action === 'add' || $editUser) ? 'active' : ''; ?>" id="userModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo $editUser ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'; ?></h3>
            <button class="modal-close" onclick="window.location.href='users.php'">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <input type="hidden" name="edit_id" value="<?php echo $editUser ? $editUser['id'] : 0; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" value="<?php echo e($editUser['nom'] ?? $_POST['nom'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" value="<?php echo e($editUser['prenom'] ?? $_POST['prenom'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?php echo e($editUser['email'] ?? $_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone" value="<?php echo e($editUser['telephone'] ?? $_POST['telephone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Rôle *</label>
                        <select name="role">
                            <option value="membre" <?php echo ($editUser['role'] ?? '') === 'membre' ? 'selected' : ''; ?>>Membre</option>
                            <option value="gestionnaire" <?php echo ($editUser['role'] ?? '') === 'gestionnaire' ? 'selected' : ''; ?>>Gestionnaire</option>
                            <?php if (isAdmin()): ?>
                                <option value="admin" <?php echo ($editUser['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mot de passe <?php echo $editUser ? '' : '*'; ?></label>
                    <input type="password" name="password" <?php echo $editUser ? '' : 'required'; ?>>
                    <?php if ($editUser): ?>
                        <p class="form-hint">Laissez vide pour conserver le mot de passe actuel.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <a href="users.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary"><?php echo $editUser ? 'Modifier' : 'Créer'; ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Liste des utilisateurs</h3>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Nouvel utilisateur</a>
    </div>
    <div class="card-body">
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#9787;</div>
                <h3>Aucun utilisateur</h3>
                <p>Créez votre premier utilisateur en cliquant sur le bouton ci-dessus.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($user['prenom'] . ' ' . $user['nom']); ?></strong>
                                    <?php if ($user['id'] === $currentUser['id']): ?>
                                        <span style="color: #999; font-size: 12px;">(vous)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <?php echo translateRole($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['actif']): ?>
                                        <span class="badge badge-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary">Modifier</a>

                                        <?php if ($user['id'] !== $currentUser['id']): ?>
                                            <a href="?action=toggle&id=<?php echo $user['id']; ?>" class="btn btn-sm <?php echo $user['actif'] ? 'btn-warning' : 'btn-success'; ?>" style="background: <?php echo $user['actif'] ? '#ff9800' : '#4caf50'; ?>;">
                                                <?php echo $user['actif'] ? 'Désactiver' : 'Activer'; ?>
                                            </a>

                                            <?php if (isAdmin() || $user['role'] !== 'admin'): ?>
                                                <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">Supprimer</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

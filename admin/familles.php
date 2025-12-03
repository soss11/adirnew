<?php
/**
 * Mini CRM - Gestion des comptes famille
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('gestionnaire');

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? '';
$familleId = (int)($_GET['id'] ?? 0);

// Supprimer une famille
if ($action === 'delete' && $familleId > 0) {
    // D'abord, retirer les membres de la famille
    $stmt = $pdo->prepare("UPDATE users SET famille_id = NULL WHERE famille_id = ?");
    $stmt->execute([$familleId]);

    $stmt = $pdo->prepare("DELETE FROM familles WHERE id = ?");
    $stmt->execute([$familleId]);
    setFlashMessage('success', 'Famille supprimée.');
    header('Location: familles.php');
    exit;
}

// Retirer un membre d'une famille
if ($action === 'remove_member' && isset($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
    $stmt = $pdo->prepare("UPDATE users SET famille_id = NULL WHERE id = ?");
    $stmt->execute([$userId]);
    setFlashMessage('success', 'Membre retiré de la famille.');
    header('Location: familles.php?action=view&id=' . $familleId);
    exit;
}

// Enregistrer une famille
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_famille'])) {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $nom = trim($_POST['nom_famille'] ?? '');
    $chefId = (int)($_POST['chef_famille_id'] ?? 0) ?: null;
    $adresse = trim($_POST['adresse'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($nom)) {
        setFlashMessage('error', 'Le nom de famille est obligatoire.');
    } else {
        if ($editId > 0) {
            $stmt = $pdo->prepare("UPDATE familles SET nom_famille = ?, chef_famille_id = ?, adresse = ?, telephone = ?, email = ?, notes = ? WHERE id = ?");
            $stmt->execute([$nom, $chefId, $adresse, $telephone, $email, $notes, $editId]);
            setFlashMessage('success', 'Famille mise à jour.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO familles (nom_famille, chef_famille_id, adresse, telephone, email, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nom, $chefId, $adresse, $telephone, $email, $notes]);
            $editId = $pdo->lastInsertId();
            setFlashMessage('success', 'Famille créée.');
        }

        // Mettre à jour le chef de famille
        if ($chefId) {
            $stmt = $pdo->prepare("UPDATE users SET famille_id = ? WHERE id = ?");
            $stmt->execute([$editId, $chefId]);
        }

        header('Location: familles.php?action=view&id=' . $editId);
        exit;
    }
}

// Ajouter des membres à une famille
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_members'])) {
    $familleId = (int)$_POST['famille_id'];
    $memberIds = $_POST['member_ids'] ?? [];

    foreach ($memberIds as $memberId) {
        $stmt = $pdo->prepare("UPDATE users SET famille_id = ? WHERE id = ?");
        $stmt->execute([$familleId, (int)$memberId]);
    }

    setFlashMessage('success', count($memberIds) . ' membre(s) ajouté(s) à la famille.');
    header('Location: familles.php?action=view&id=' . $familleId);
    exit;
}

$pageTitle = 'Comptes Famille';
require_once __DIR__ . '/../includes/header.php';

// Liste des familles
$familles = $pdo->query("
    SELECT f.*,
           (SELECT CONCAT(prenom, ' ', nom) FROM users WHERE id = f.chef_famille_id) as chef_nom,
           (SELECT COUNT(*) FROM users WHERE famille_id = f.id) as nb_membres
    FROM familles f
    ORDER BY f.nom_famille
")->fetchAll();

// Détail d'une famille
$viewFamille = null;
$membresFamille = [];
if ($action === 'view' && $familleId > 0) {
    $stmt = $pdo->prepare("SELECT f.*, u.nom as chef_nom, u.prenom as chef_prenom
                           FROM familles f
                           LEFT JOIN users u ON f.chef_famille_id = u.id
                           WHERE f.id = ?");
    $stmt->execute([$familleId]);
    $viewFamille = $stmt->fetch();

    if ($viewFamille) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE famille_id = ? ORDER BY date_naissance");
        $stmt->execute([$familleId]);
        $membresFamille = $stmt->fetchAll();
    }
}

// Famille à éditer
$editFamille = null;
if ($action === 'edit' && $familleId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM familles WHERE id = ?");
    $stmt->execute([$familleId]);
    $editFamille = $stmt->fetch();
}

// Liste des membres sans famille (pour l'ajout)
$membresSansFamille = $pdo->query("SELECT id, nom, prenom, email FROM users WHERE famille_id IS NULL AND role = 'membre' AND actif = 1 ORDER BY nom")->fetchAll();
$tousMembres = $pdo->query("SELECT id, nom, prenom, email FROM users WHERE role = 'membre' AND actif = 1 ORDER BY nom")->fetchAll();
?>

<style>
.family-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.2s;
}
.family-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.family-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.family-name {
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
}
.family-meta {
    display: flex;
    gap: 20px;
    color: #6b7280;
    font-size: 14px;
}
.member-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
}
.member-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f3f4f6;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
}
.member-chip .avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 500;
}
.member-chip.chef {
    background: #dbeafe;
    border: 1px solid #3b82f6;
}
.member-chip.chef .avatar {
    background: #3b82f6;
}
.member-detail-card {
    background: #f9fafb;
    border-radius: 10px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 10px;
}
.member-detail-card .avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 600;
}
.member-detail-card .info { flex: 1; }
.member-detail-card .info h4 { margin: 0 0 5px; }
.member-detail-card .info p { margin: 0; color: #6b7280; font-size: 14px; }
</style>

<?php if ($action === 'view' && $viewFamille): ?>
<!-- Vue détail famille -->
<div class="card">
    <div class="card-header">
        <h3>Famille <?php echo e($viewFamille['nom_famille']); ?></h3>
        <div class="btn-group">
            <a href="?action=edit&id=<?php echo $viewFamille['id']; ?>" class="btn btn-sm btn-primary">Modifier</a>
            <button onclick="openModal('addMembersModal')" class="btn btn-sm btn-success">+ Ajouter membre</button>
            <a href="familles.php" class="btn btn-sm btn-secondary">Retour</a>
        </div>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
            <div>
                <h4 style="margin-bottom: 15px;">Informations</h4>
                <table class="info-table">
                    <tr>
                        <td>Chef de famille</td>
                        <td><?php echo e(($viewFamille['chef_prenom'] ?? '') . ' ' . ($viewFamille['chef_nom'] ?? '-')); ?></td>
                    </tr>
                    <tr>
                        <td>Téléphone</td>
                        <td><?php echo e($viewFamille['telephone'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td><?php echo e($viewFamille['email'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <td>Adresse</td>
                        <td><?php echo e($viewFamille['adresse'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <td>Membres</td>
                        <td><?php echo count($membresFamille); ?> personne(s)</td>
                    </tr>
                </table>

                <?php if ($viewFamille['notes']): ?>
                    <div style="margin-top: 20px;">
                        <strong>Notes :</strong><br>
                        <?php echo nl2br(e($viewFamille['notes'])); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h4 style="margin-bottom: 15px;">Membres de la famille (<?php echo count($membresFamille); ?>)</h4>

                <?php if (empty($membresFamille)): ?>
                    <p style="color: #666;">Aucun membre dans cette famille.</p>
                <?php else: ?>
                    <?php foreach ($membresFamille as $membre): ?>
                        <div class="member-detail-card">
                            <div class="avatar"><?php echo e(substr($membre['prenom'] ?: $membre['nom'], 0, 1)); ?></div>
                            <div class="info">
                                <h4>
                                    <?php echo e($membre['prenom'] . ' ' . $membre['nom']); ?>
                                    <?php if ($membre['id'] == $viewFamille['chef_famille_id']): ?>
                                        <span class="badge badge-info" style="font-size: 10px;">Chef</span>
                                    <?php endif; ?>
                                </h4>
                                <p>
                                    <?php echo e($membre['email']); ?>
                                    <?php if ($membre['date_naissance']): ?>
                                        | Né(e) le <?php echo date('d/m/Y', strtotime($membre['date_naissance'])); ?>
                                        (<?php echo floor((time() - strtotime($membre['date_naissance'])) / 31536000); ?> ans)
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <a href="membres.php?action=edit&id=<?php echo $membre['id']; ?>" class="btn btn-sm btn-secondary">Voir</a>
                                <a href="?action=remove_member&id=<?php echo $viewFamille['id']; ?>&user_id=<?php echo $membre['id']; ?>"
                                   onclick="return confirm('Retirer ce membre de la famille ?')"
                                   class="btn btn-sm" style="color: #dc2626;">Retirer</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter membres -->
<div class="modal-overlay" id="addMembersModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Ajouter des membres</h3>
            <button class="modal-close" onclick="closeModal('addMembersModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="add_members" value="1">
                <input type="hidden" name="famille_id" value="<?php echo $viewFamille['id']; ?>">

                <p style="margin-bottom: 15px;">Sélectionnez les membres à ajouter :</p>

                <?php if (empty($membresSansFamille)): ?>
                    <p style="color: #666;">Tous les membres sont déjà dans une famille.</p>
                <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($membresSansFamille as $m): ?>
                            <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f9f9f9; border-radius: 8px; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" name="member_ids[]" value="<?php echo $m['id']; ?>">
                                <span><?php echo e($m['prenom'] . ' ' . $m['nom']); ?></span>
                                <span style="color: #666; font-size: 12px;"><?php echo e($m['email']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addMembersModal')">Annuler</button>
                <button type="submit" class="btn btn-primary" <?php echo empty($membresSansFamille) ? 'disabled' : ''; ?>>Ajouter</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire famille -->
<div class="card">
    <div class="card-header">
        <h3><?php echo $editFamille ? 'Modifier la famille' : 'Nouvelle famille'; ?></h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="save_famille" value="1">
            <input type="hidden" name="edit_id" value="<?php echo $editFamille['id'] ?? 0; ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Nom de famille *</label>
                    <input type="text" name="nom_famille" value="<?php echo e($editFamille['nom_famille'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Chef de famille</label>
                    <select name="chef_famille_id">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($tousMembres as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo ($editFamille['chef_famille_id'] ?? 0) == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo e($m['prenom'] . ' ' . $m['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" name="telephone" value="<?php echo e($editFamille['telephone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo e($editFamille['email'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Adresse</label>
                <textarea name="adresse" rows="2"><?php echo e($editFamille['adresse'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3"><?php echo e($editFamille['notes'] ?? ''); ?></textarea>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="familles.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- Liste des familles -->
<div class="card">
    <div class="card-header">
        <h3>Comptes Famille (<?php echo count($familles); ?>)</h3>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Nouvelle famille</a>
    </div>
    <div class="card-body">
        <?php if (empty($familles)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128106;</div>
                <h3>Aucune famille</h3>
                <p>Créez des comptes famille pour regrouper les membres.</p>
                <a href="?action=add" class="btn btn-primary">Créer une famille</a>
            </div>
        <?php else: ?>
            <?php foreach ($familles as $famille): ?>
                <div class="family-card">
                    <div class="family-header">
                        <div>
                            <div class="family-name"><?php echo e($famille['nom_famille']); ?></div>
                            <div class="family-meta">
                                <?php if ($famille['chef_nom']): ?>
                                    <span>Chef : <?php echo e($famille['chef_nom']); ?></span>
                                <?php endif; ?>
                                <span><?php echo $famille['nb_membres']; ?> membre(s)</span>
                            </div>
                        </div>
                        <div class="btn-group">
                            <a href="?action=view&id=<?php echo $famille['id']; ?>" class="btn btn-sm btn-primary">Voir</a>
                            <a href="?action=edit&id=<?php echo $famille['id']; ?>" class="btn btn-sm btn-secondary">Modifier</a>
                            <a href="?action=delete&id=<?php echo $famille['id']; ?>" onclick="return confirm('Supprimer cette famille ?')" class="btn btn-sm" style="color:#dc2626;">Supprimer</a>
                        </div>
                    </div>

                    <?php
                    // Récupérer les membres de cette famille
                    $stmt = $pdo->prepare("SELECT id, nom, prenom FROM users WHERE famille_id = ? LIMIT 5");
                    $stmt->execute([$famille['id']]);
                    $membres = $stmt->fetchAll();
                    ?>
                    <?php if (!empty($membres)): ?>
                        <div class="member-list">
                            <?php foreach ($membres as $m): ?>
                                <div class="member-chip <?php echo $m['id'] == $famille['chef_famille_id'] ? 'chef' : ''; ?>">
                                    <div class="avatar"><?php echo e(substr($m['prenom'] ?: $m['nom'], 0, 1)); ?></div>
                                    <span><?php echo e($m['prenom'] . ' ' . $m['nom']); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($famille['nb_membres'] > 5): ?>
                                <div class="member-chip" style="background: #e5e7eb;">
                                    +<?php echo $famille['nb_membres'] - 5; ?> autre(s)
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<style>
.info-table { width: 100%; }
.info-table td { padding: 8px 0; border-bottom: 1px solid #eee; }
.info-table td:first-child { font-weight: 500; color: #666; width: 120px; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

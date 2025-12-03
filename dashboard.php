<?php
/**
 * Mini CRM - Tableau de bord
 */

$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/includes/header.php';

requireLogin();

$userCounts = countUsersByRole();
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">&#9787;</div>
        <div class="stat-content">
            <h4><?php echo $userCounts['total']; ?></h4>
            <p>Utilisateurs total</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">&#9733;</div>
        <div class="stat-content">
            <h4><?php echo $userCounts['admin']; ?></h4>
            <p>Administrateurs</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">&#9998;</div>
        <div class="stat-content">
            <h4><?php echo $userCounts['gestionnaire']; ?></h4>
            <p>Gestionnaires</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon info">&#9734;</div>
        <div class="stat-content">
            <h4><?php echo $userCounts['membre']; ?></h4>
            <p>Membres</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Bienvenue, <?php echo e($currentUser['prenom'] ?: $currentUser['nom']); ?> !</h3>
    </div>
    <div class="card-body">
        <p>Vous êtes connecté en tant que <strong><?php echo translateRole($currentUser['role']); ?></strong>.</p>

        <?php if ($currentUser['role'] === 'admin'): ?>
            <div style="margin-top: 20px;">
                <h4 style="margin-bottom: 15px;">Actions rapides</h4>
                <div class="btn-group">
                    <a href="admin/users.php" class="btn btn-primary">Gérer les utilisateurs</a>
                    <a href="admin/settings.php" class="btn btn-secondary">Paramètres</a>
                </div>
            </div>
        <?php elseif ($currentUser['role'] === 'gestionnaire'): ?>
            <div style="margin-top: 20px;">
                <h4 style="margin-bottom: 15px;">Actions rapides</h4>
                <div class="btn-group">
                    <a href="admin/users.php" class="btn btn-primary">Gérer les membres</a>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 30px;">
            <h4 style="margin-bottom: 15px;">Informations de l'association</h4>
            <table>
                <tr>
                    <td style="width: 150px; font-weight: 500;">Nom</td>
                    <td><?php echo e($asso['nom'] ?: '-'); ?></td>
                </tr>
                <tr>
                    <td style="font-weight: 500;">Adresse</td>
                    <td><?php echo e($asso['adresse'] ?: '-'); ?></td>
                </tr>
                <tr>
                    <td style="font-weight: 500;">Téléphone</td>
                    <td><?php echo e($asso['tel'] ?: '-'); ?></td>
                </tr>
                <tr>
                    <td style="font-weight: 500;">Email</td>
                    <td><?php echo e($asso['email'] ?: '-'); ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<div class="card">
    <div class="card-header">
        <h3>Dernières connexions</h3>
    </div>
    <div class="card-body">
        <?php
        $stmt = $pdo->query("
            SELECT l.*, u.nom, u.prenom, u.email
            FROM login_logs l
            LEFT JOIN users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
            LIMIT 10
        ");
        $logs = $stmt->fetchAll();
        ?>

        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128196;</div>
                <h3>Aucune connexion enregistrée</h3>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Date</th>
                            <th>IP</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <?php if ($log['nom']): ?>
                                        <?php echo e($log['prenom'] . ' ' . $log['nom']); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Utilisateur inconnu</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDateTime($log['created_at']); ?></td>
                                <td><code><?php echo e($log['ip_address']); ?></code></td>
                                <td>
                                    <?php if ($log['success']): ?>
                                        <span class="badge badge-success">Succès</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Échec</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Mini CRM - Tableau de bord
 */

$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/includes/header.php';

requireLogin();

// Statistiques générales
$stats = [];

// Nombre de membres
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'membre' AND actif = 1");
$stats['membres'] = $stmt->fetchColumn();

// Cotisations à jour
$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM cotisations WHERE statut = 'paye' AND date_fin >= CURDATE()");
$stats['cotisations_jour'] = $stmt->fetchColumn();

// Cotisations à renouveler (dans les 30 jours)
$stmt = $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'paye' AND date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$stats['cotisations_renouveler'] = $stmt->fetchColumn();

// Cotisations en attente de paiement
$stmt = $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'en_attente'");
$stats['cotisations_attente'] = $stmt->fetchColumn();

// Événements à venir
$stmt = $pdo->query("SELECT COUNT(*) FROM evenements WHERE date_debut >= NOW() AND statut IN ('publie', 'complet')");
$stats['evenements_venir'] = $stmt->fetchColumn();

// Revenus de l'année
$stmt = $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM cotisations WHERE statut = 'paye' AND YEAR(date_paiement) = YEAR(CURDATE())");
$stats['revenus_cotisations'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM inscriptions WHERE statut IN ('confirme', 'present') AND YEAR(created_at) = YEAR(CURDATE())");
$stats['revenus_evenements'] = $stmt->fetchColumn();

$stats['revenus_total'] = $stats['revenus_cotisations'] + $stats['revenus_evenements'];

// Prochains événements
$stmt = $pdo->query("SELECT e.*,
    (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut != 'annule') as nb_inscrits
    FROM evenements e
    WHERE e.date_debut >= NOW() AND e.statut IN ('publie', 'complet')
    ORDER BY e.date_debut ASC LIMIT 5");
$prochains_evenements = $stmt->fetchAll();

// Cotisations à renouveler
$stmt = $pdo->query("SELECT c.*, u.nom, u.prenom, u.email, ct.nom as type_nom
    FROM cotisations c
    JOIN users u ON c.user_id = u.id
    JOIN cotisation_types ct ON c.cotisation_type_id = ct.id
    WHERE c.statut = 'paye' AND c.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY c.date_fin ASC LIMIT 10");
$cotisations_renouveler = $stmt->fetchAll();

// Nouveaux membres (30 derniers jours)
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'membre' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY created_at DESC LIMIT 5");
$nouveaux_membres = $stmt->fetchAll();

// Statistiques par pays d'origine
$stmt = $pdo->query("SELECT pays_origine, COUNT(*) as count FROM users WHERE role = 'membre' AND actif = 1 AND pays_origine IS NOT NULL AND pays_origine != '' GROUP BY pays_origine ORDER BY count DESC LIMIT 5");
$stats_pays = $stmt->fetchAll();

$eventTypes = [
    'spectacle' => ['label' => 'Spectacle', 'color' => '#e91e63'],
    'soiree' => ['label' => 'Soirée', 'color' => '#9c27b0'],
    'tombola' => ['label' => 'Tombola', 'color' => '#ff9800'],
    'atelier' => ['label' => 'Atelier', 'color' => '#4caf50'],
    'reunion' => ['label' => 'Réunion', 'color' => '#2196f3'],
    'autre' => ['label' => 'Autre', 'color' => '#607d8b']
];
?>

<!-- Stats principales -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">&#9787;</div>
        <div class="stat-content">
            <h4><?php echo $stats['membres']; ?></h4>
            <p>Membres actifs</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">&#10004;</div>
        <div class="stat-content">
            <h4><?php echo $stats['cotisations_jour']; ?></h4>
            <p>Cotisations à jour</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon info">&#9734;</div>
        <div class="stat-content">
            <h4><?php echo $stats['evenements_venir']; ?></h4>
            <p>Événements à venir</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">&#8364;</div>
        <div class="stat-content">
            <h4><?php echo number_format($stats['revenus_total'], 0, ',', ' '); ?> €</h4>
            <p>Revenus <?php echo date('Y'); ?></p>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;">

    <!-- Prochains événements -->
    <div class="card">
        <div class="card-header">
            <h3>Prochains événements</h3>
            <?php if (isGestionnaire()): ?>
                <a href="admin/evenements.php" class="btn btn-sm btn-secondary">Voir tout</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($prochains_evenements)): ?>
                <p style="color:#666;text-align:center;padding:20px;">Aucun événement à venir</p>
            <?php else: ?>
                <?php foreach ($prochains_evenements as $event): ?>
                    <div style="display:flex;gap:15px;padding:12px 0;border-bottom:1px solid #eee;">
                        <div style="min-width:50px;text-align:center;background:var(--light-gray);border-radius:8px;padding:8px;">
                            <div style="font-size:20px;font-weight:bold;color:var(--primary);"><?php echo date('d', strtotime($event['date_debut'])); ?></div>
                            <div style="font-size:11px;text-transform:uppercase;color:#666;"><?php echo date('M', strtotime($event['date_debut'])); ?></div>
                        </div>
                        <div style="flex:1;">
                            <strong><?php echo e($event['titre']); ?></strong>
                            <div style="font-size:13px;color:#666;">
                                <?php
                                $type = $eventTypes[$event['type']] ?? $eventTypes['autre'];
                                echo "<span style='color:{$type['color']};'>{$type['label']}</span>";
                                ?>
                                • <?php echo date('H:i', strtotime($event['date_debut'])); ?>
                                <?php if ($event['lieu']): ?> • <?php echo e($event['lieu']); ?><?php endif; ?>
                            </div>
                            <div style="font-size:12px;color:#888;margin-top:3px;">
                                <?php echo $event['nb_inscrits']; ?> inscrit(s)
                                <?php if ($event['places_max'] > 0): ?>
                                    / <?php echo $event['places_max']; ?> places
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alertes cotisations -->
    <div class="card">
        <div class="card-header">
            <h3>Cotisations à renouveler</h3>
            <?php if (isGestionnaire()): ?>
                <a href="admin/cotisations.php?expire=bientot" class="btn btn-sm btn-secondary">Voir tout</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($stats['cotisations_attente'] > 0): ?>
                <div style="padding:12px;background:rgba(255,152,0,0.1);border-radius:8px;margin-bottom:15px;">
                    <strong style="color:#e65100;"><?php echo $stats['cotisations_attente']; ?> cotisation(s) en attente de paiement</strong>
                    <?php if (isGestionnaire()): ?>
                        <a href="admin/cotisations.php?statut=en_attente" style="float:right;font-size:13px;">Voir &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cotisations_renouveler)): ?>
                <p style="color:#666;text-align:center;padding:20px;">Aucune cotisation à renouveler dans les 30 prochains jours</p>
            <?php else: ?>
                <?php foreach ($cotisations_renouveler as $cot): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eee;">
                        <div>
                            <strong><?php echo e($cot['prenom'] . ' ' . $cot['nom']); ?></strong>
                            <div style="font-size:12px;color:#666;"><?php echo e($cot['type_nom']); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="color:#e65100;font-weight:500;">Expire le <?php echo formatDate($cot['date_fin']); ?></div>
                            <?php
                            $jours = floor((strtotime($cot['date_fin']) - time()) / 86400);
                            if ($jours <= 7): ?>
                                <span class="badge badge-danger">Dans <?php echo $jours; ?> jour(s)</span>
                            <?php elseif ($jours <= 14): ?>
                                <span class="badge" style="background:rgba(255,152,0,0.15);color:#e65100;">Dans <?php echo $jours; ?> jours</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:20px;">

    <!-- Nouveaux membres -->
    <div class="card">
        <div class="card-header">
            <h3>Nouveaux membres</h3>
        </div>
        <div class="card-body">
            <?php if (empty($nouveaux_membres)): ?>
                <p style="color:#666;text-align:center;padding:20px;">Aucun nouveau membre ce mois-ci</p>
            <?php else: ?>
                <?php foreach ($nouveaux_membres as $membre): ?>
                    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #eee;">
                        <div style="width:35px;height:35px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                            <?php echo e(substr($membre['prenom'] ?: $membre['nom'], 0, 1)); ?>
                        </div>
                        <div style="flex:1;">
                            <strong><?php echo e($membre['prenom'] . ' ' . $membre['nom']); ?></strong>
                            <div style="font-size:12px;color:#666;">Inscrit le <?php echo formatDate($membre['created_at']); ?></div>
                        </div>
                        <?php if (isGestionnaire()): ?>
                            <a href="admin/membres.php?action=view&id=<?php echo $membre['id']; ?>" class="btn btn-sm btn-secondary">Voir</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats par pays -->
    <div class="card">
        <div class="card-header">
            <h3>Membres par pays d'origine</h3>
        </div>
        <div class="card-body">
            <?php if (empty($stats_pays)): ?>
                <p style="color:#666;text-align:center;padding:20px;">Aucune donnée disponible</p>
            <?php else: ?>
                <?php
                $maxCount = max(array_column($stats_pays, 'count'));
                $colors = ['#667eea', '#764ba2', '#4caf50', '#ff9800', '#2196f3'];
                ?>
                <?php foreach ($stats_pays as $i => $pays): ?>
                    <div style="margin-bottom:15px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                            <span><?php echo e($pays['pays_origine']); ?></span>
                            <strong><?php echo $pays['count']; ?></strong>
                        </div>
                        <div style="background:#eee;border-radius:10px;height:8px;overflow:hidden;">
                            <div style="background:<?php echo $colors[$i % count($colors)]; ?>;height:100%;width:<?php echo ($pays['count'] / $maxCount * 100); ?>%;border-radius:10px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Revenus détaillés -->
    <div class="card">
        <div class="card-header">
            <h3>Revenus <?php echo date('Y'); ?></h3>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:15px;">
                <div style="padding:15px;background:rgba(102,126,234,0.1);border-radius:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span>Cotisations</span>
                        <strong style="font-size:18px;"><?php echo number_format($stats['revenus_cotisations'], 2, ',', ' '); ?> €</strong>
                    </div>
                </div>
                <div style="padding:15px;background:rgba(76,175,80,0.1);border-radius:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span>Événements</span>
                        <strong style="font-size:18px;"><?php echo number_format($stats['revenus_evenements'], 2, ',', ' '); ?> €</strong>
                    </div>
                </div>
                <div style="padding:15px;background:#f5f5f5;border-radius:8px;border:2px solid var(--primary);">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <strong>Total</strong>
                        <strong style="font-size:22px;color:var(--primary);"><?php echo number_format($stats['revenus_total'], 2, ',', ' '); ?> €</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php if (isAdmin()): ?>
<div class="card" style="margin-top:20px;">
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
                <p>Aucune connexion enregistrée</p>
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

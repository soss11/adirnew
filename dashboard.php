<?php
/**
 * Mini CRM - Tableau de bord
 */

$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/includes/header.php';

requireLogin();

$eventTypes = [
    'spectacle' => ['label' => 'Spectacle', 'color' => '#e91e63'],
    'soiree' => ['label' => 'Soirée', 'color' => '#9c27b0'],
    'tombola' => ['label' => 'Tombola', 'color' => '#ff9800'],
    'atelier' => ['label' => 'Atelier', 'color' => '#4caf50'],
    'reunion' => ['label' => 'Réunion', 'color' => '#2196f3'],
    'autre' => ['label' => 'Autre', 'color' => '#607d8b']
];

// ============================================
// VUE MEMBRE (utilisateur simple)
// ============================================
if ($currentUser['role'] === 'membre'):

// Ma cotisation actuelle
$stmt = $pdo->prepare("SELECT c.*, ct.nom as type_nom FROM cotisations c
    JOIN cotisation_types ct ON c.cotisation_type_id = ct.id
    WHERE c.user_id = ? AND c.statut = 'paye'
    ORDER BY c.date_fin DESC LIMIT 1");
$stmt->execute([$currentUser['id']]);
$maCotisation = $stmt->fetch();

$cotisationStatut = 'aucune';
$cotisationMessage = 'Vous n\'avez pas de cotisation active.';
if ($maCotisation) {
    if (strtotime($maCotisation['date_fin']) < time()) {
        $cotisationStatut = 'expiree';
        $cotisationMessage = 'Votre cotisation a expiré le ' . formatDate($maCotisation['date_fin']);
    } elseif (strtotime($maCotisation['date_fin']) < strtotime('+30 days')) {
        $cotisationStatut = 'expire_bientot';
        $jours = floor((strtotime($maCotisation['date_fin']) - time()) / 86400);
        $cotisationMessage = 'Votre cotisation expire dans ' . $jours . ' jours';
    } else {
        $cotisationStatut = 'valide';
        $cotisationMessage = 'Cotisation valide jusqu\'au ' . formatDate($maCotisation['date_fin']);
    }
}

// Mes inscriptions aux événements
$stmt = $pdo->prepare("SELECT i.*, e.titre, e.date_debut, e.lieu, e.type
    FROM inscriptions i
    JOIN evenements e ON i.evenement_id = e.id
    WHERE i.user_id = ? AND e.date_debut >= NOW()
    ORDER BY e.date_debut ASC");
$stmt->execute([$currentUser['id']]);
$mesInscriptions = $stmt->fetchAll();

// Événements à venir (auxquels je ne suis pas inscrit)
$stmt = $pdo->prepare("SELECT e.*,
    (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut != 'annule') as nb_inscrits,
    (SELECT id FROM inscriptions i WHERE i.evenement_id = e.id AND i.user_id = ?) as mon_inscription
    FROM evenements e
    WHERE e.date_debut >= NOW() AND e.statut IN ('publie', 'complet')
    ORDER BY e.date_debut ASC LIMIT 10");
$stmt->execute([$currentUser['id']]);
$evenements = $stmt->fetchAll();
?>

<!-- Bienvenue -->
<div class="card">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
        <div>
            <h2 style="margin-bottom:10px;">Bienvenue, <?php echo e($currentUser['prenom'] ?: $currentUser['nom']); ?> !</h2>
            <p style="color:#666;">Voici votre espace personnel.</p>
        </div>
        <div class="btn-group">
            <a href="admin/carte-membre.php" class="btn btn-primary">Ma carte membre</a>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:20px;">

    <!-- Ma cotisation -->
    <div class="card">
        <div class="card-header">
            <h3>Ma cotisation</h3>
        </div>
        <div class="card-body">
            <?php if ($cotisationStatut === 'valide'): ?>
                <div style="padding:20px;background:rgba(76,175,80,0.1);border-radius:10px;text-align:center;">
                    <div style="font-size:40px;margin-bottom:10px;">✓</div>
                    <div style="font-size:18px;font-weight:bold;color:#2e7d32;">Cotisation à jour</div>
                    <p style="color:#666;margin-top:5px;"><?php echo e($maCotisation['type_nom']); ?></p>
                    <p style="color:#666;">Valide jusqu'au <strong><?php echo formatDate($maCotisation['date_fin']); ?></strong></p>
                </div>
            <?php elseif ($cotisationStatut === 'expire_bientot'): ?>
                <div style="padding:20px;background:rgba(255,152,0,0.1);border-radius:10px;text-align:center;">
                    <div style="font-size:40px;margin-bottom:10px;">⚠</div>
                    <div style="font-size:18px;font-weight:bold;color:#e65100;">Expire bientôt</div>
                    <p style="color:#666;margin-top:5px;"><?php echo $cotisationMessage; ?></p>
                    <p style="color:#666;margin-top:10px;">Pensez à renouveler votre cotisation !</p>
                </div>
            <?php elseif ($cotisationStatut === 'expiree'): ?>
                <div style="padding:20px;background:rgba(244,67,54,0.1);border-radius:10px;text-align:center;">
                    <div style="font-size:40px;margin-bottom:10px;">✗</div>
                    <div style="font-size:18px;font-weight:bold;color:#c62828;">Cotisation expirée</div>
                    <p style="color:#666;margin-top:5px;"><?php echo $cotisationMessage; ?></p>
                    <p style="color:#666;margin-top:10px;">Contactez l'association pour renouveler.</p>
                </div>
            <?php else: ?>
                <div style="padding:20px;background:#f5f5f5;border-radius:10px;text-align:center;">
                    <div style="font-size:40px;margin-bottom:10px;">?</div>
                    <div style="font-size:18px;font-weight:bold;color:#666;">Pas de cotisation</div>
                    <p style="color:#666;margin-top:5px;">Contactez l'association pour adhérer.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mes informations -->
    <div class="card">
        <div class="card-header">
            <h3>Mes informations</h3>
            <a href="profile.php" class="btn btn-sm btn-secondary">Modifier</a>
        </div>
        <div class="card-body">
            <table style="width:100%;">
                <tr><td style="padding:8px 0;color:#666;">Nom</td><td style="padding:8px 0;"><strong><?php echo e($currentUser['prenom'] . ' ' . $currentUser['nom']); ?></strong></td></tr>
                <tr><td style="padding:8px 0;color:#666;">Email</td><td style="padding:8px 0;"><?php echo e($currentUser['email']); ?></td></tr>
                <tr><td style="padding:8px 0;color:#666;">Téléphone</td><td style="padding:8px 0;"><?php echo e($currentUser['telephone'] ?: '-'); ?></td></tr>
                <tr><td style="padding:8px 0;color:#666;">Membre depuis</td><td style="padding:8px 0;"><?php echo formatDate($currentUser['created_at']); ?></td></tr>
            </table>
        </div>
    </div>

</div>

<!-- Mes inscriptions aux événements -->
<?php if (!empty($mesInscriptions)): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Mes inscriptions</h3>
    </div>
    <div class="card-body">
        <div style="display:grid;gap:15px;">
            <?php foreach ($mesInscriptions as $insc): ?>
                <div style="display:flex;gap:15px;padding:15px;background:#f9f9f9;border-radius:10px;">
                    <div style="min-width:50px;text-align:center;background:var(--primary);color:#fff;border-radius:8px;padding:10px;">
                        <div style="font-size:20px;font-weight:bold;"><?php echo date('d', strtotime($insc['date_debut'])); ?></div>
                        <div style="font-size:11px;text-transform:uppercase;"><?php echo date('M', strtotime($insc['date_debut'])); ?></div>
                    </div>
                    <div style="flex:1;">
                        <strong><?php echo e($insc['titre']); ?></strong>
                        <div style="font-size:13px;color:#666;margin-top:3px;">
                            <?php echo date('H:i', strtotime($insc['date_debut'])); ?>
                            <?php if ($insc['lieu']): ?> • <?php echo e($insc['lieu']); ?><?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <?php if ($insc['statut'] === 'confirme'): ?>
                            <span class="badge badge-success">Confirmé</span>
                            <?php if (!empty($insc['ticket_code'])): ?>
                                <a href="billet.php?code=<?php echo urlencode($insc['ticket_code']); ?>" class="btn btn-sm btn-primary">Mon billet</a>
                            <?php endif; ?>
                        <?php elseif ($insc['statut'] === 'en_attente'): ?>
                            <span class="badge" style="background:rgba(255,152,0,0.15);color:#e65100;">En attente</span>
                        <?php elseif ($insc['statut'] === 'liste_attente'): ?>
                            <span class="badge" style="background:rgba(99,102,241,0.15);color:#6366f1;">Liste d'attente</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Événements à venir -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Événements à venir</h3>
    </div>
    <div class="card-body">
        <?php if (empty($evenements)): ?>
            <p style="color:#666;text-align:center;padding:30px;">Aucun événement prévu pour le moment.</p>
        <?php else: ?>
            <div style="display:grid;gap:15px;">
                <?php foreach ($evenements as $event): ?>
                    <div style="display:flex;gap:15px;padding:15px;background:#f9f9f9;border-radius:10px;align-items:center;">
                        <div style="min-width:50px;text-align:center;background:var(--light-gray);border-radius:8px;padding:10px;">
                            <div style="font-size:20px;font-weight:bold;color:var(--primary);"><?php echo date('d', strtotime($event['date_debut'])); ?></div>
                            <div style="font-size:11px;text-transform:uppercase;color:#666;"><?php echo date('M', strtotime($event['date_debut'])); ?></div>
                        </div>
                        <div style="flex:1;">
                            <strong><?php echo e($event['titre']); ?></strong>
                            <div style="font-size:13px;color:#666;margin-top:3px;">
                                <?php
                                $type = $eventTypes[$event['type']] ?? $eventTypes['autre'];
                                echo "<span style='color:{$type['color']};'>{$type['label']}</span>";
                                ?>
                                • <?php echo date('H:i', strtotime($event['date_debut'])); ?>
                                <?php if ($event['lieu']): ?> • <?php echo e($event['lieu']); ?><?php endif; ?>
                            </div>
                            <?php if ($event['prix_membre'] > 0): ?>
                                <div style="font-size:12px;color:#888;margin-top:3px;">
                                    Tarif membre : <?php echo number_format($event['prix_membre'], 2); ?> €
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($event['mon_inscription']): ?>
                                <span class="badge badge-success">Inscrit</span>
                            <?php elseif ($event['statut'] === 'complet'): ?>
                                <span class="badge badge-danger">Complet</span>
                            <?php else: ?>
                                <span style="font-size:12px;color:#666;">
                                    <?php echo $event['nb_inscrits']; ?><?php if ($event['places_max']): ?>/<?php echo $event['places_max']; ?><?php endif; ?> inscrit(s)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// ============================================
// VUE ADMIN / GESTIONNAIRE
// ============================================
else:

// Statistiques générales
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'membre' AND actif = 1");
$stats['membres'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM cotisations WHERE statut = 'paye' AND date_fin >= CURDATE()");
$stats['cotisations_jour'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'paye' AND date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$stats['cotisations_renouveler'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'en_attente'");
$stats['cotisations_attente'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM evenements WHERE date_debut >= NOW() AND statut IN ('publie', 'complet')");
$stats['evenements_venir'] = $stmt->fetchColumn();

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

// Anniversaires à venir (7 prochains jours)
$stmt = $pdo->query("SELECT * FROM users
    WHERE role = 'membre' AND actif = 1 AND date_naissance IS NOT NULL
    AND (
        (MONTH(date_naissance) = MONTH(CURDATE()) AND DAY(date_naissance) >= DAY(CURDATE()))
        OR (MONTH(date_naissance) = MONTH(DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AND DAY(date_naissance) <= DAY(DATE_ADD(CURDATE(), INTERVAL 7 DAY)))
    )
    ORDER BY MONTH(date_naissance), DAY(date_naissance) LIMIT 10");
$anniversaires = $stmt->fetchAll();

// Statistiques par pays d'origine
$stmt = $pdo->query("SELECT pays_origine, COUNT(*) as count FROM users WHERE role = 'membre' AND actif = 1 AND pays_origine IS NOT NULL AND pays_origine != '' GROUP BY pays_origine ORDER BY count DESC LIMIT 5");
$stats_pays = $stmt->fetchAll();
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
            <a href="admin/evenements.php" class="btn btn-sm btn-secondary">Voir tout</a>
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
            <a href="admin/cotisations.php?expire=bientot" class="btn btn-sm btn-secondary">Voir tout</a>
        </div>
        <div class="card-body">
            <?php if ($stats['cotisations_attente'] > 0): ?>
                <div style="padding:12px;background:rgba(255,152,0,0.1);border-radius:8px;margin-bottom:15px;">
                    <strong style="color:#e65100;"><?php echo $stats['cotisations_attente']; ?> cotisation(s) en attente de paiement</strong>
                    <a href="admin/cotisations.php?statut=en_attente" style="float:right;font-size:13px;">Voir →</a>
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

    <!-- Anniversaires -->
    <?php if (!empty($anniversaires)): ?>
    <div class="card">
        <div class="card-header">
            <h3>🎂 Anniversaires à venir</h3>
        </div>
        <div class="card-body">
            <?php foreach ($anniversaires as $anniv):
                $jourAnniv = date('d/m', strtotime($anniv['date_naissance']));
                $age = date('Y') - date('Y', strtotime($anniv['date_naissance']));
                $estAujourdhui = date('m-d') === date('m-d', strtotime($anniv['date_naissance']));
            ?>
                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #eee;">
                    <div style="width:35px;height:35px;border-radius:50%;background:<?php echo $estAujourdhui ? '#ffd700' : 'var(--primary-bg)'; ?>;display:flex;align-items:center;justify-content:center;font-size:16px;">
                        <?php echo $estAujourdhui ? '🎉' : '🎂'; ?>
                    </div>
                    <div style="flex:1;">
                        <strong><?php echo e($anniv['prenom'] . ' ' . $anniv['nom']); ?></strong>
                        <?php if ($estAujourdhui): ?>
                            <span class="badge badge-warning" style="margin-left:5px;">Aujourd'hui !</span>
                        <?php endif; ?>
                        <div style="font-size:12px;color:#666;">
                            <?php echo $jourAnniv; ?> - <?php echo $age; ?> ans
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

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
                        <a href="admin/membres.php?action=view&id=<?php echo $membre['id']; ?>" class="btn btn-sm btn-secondary">Voir</a>
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

<?php endif; // Fin de la condition role ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

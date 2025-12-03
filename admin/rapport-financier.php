<?php
/**
 * Mini CRM - Rapport Financier Annuel
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$annee = (int)($_GET['annee'] ?? date('Y'));

// Récupérer les années disponibles
$annees = $pdo->query("SELECT DISTINCT YEAR(date_depense) as annee FROM depenses
    UNION SELECT DISTINCT YEAR(date_paiement) FROM cotisations WHERE date_paiement IS NOT NULL
    UNION SELECT DISTINCT YEAR(date_paiement) FROM inscriptions WHERE date_paiement IS NOT NULL
    ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);

if (empty($annees)) $annees = [date('Y')];

// === RECETTES ===

// Cotisations
$stmt = $pdo->prepare("SELECT SUM(montant) as total, COUNT(*) as nombre
    FROM cotisations
    WHERE statut = 'paye' AND YEAR(date_paiement) = ?");
$stmt->execute([$annee]);
$cotisations = $stmt->fetch();

// Inscriptions événements
$stmt = $pdo->prepare("SELECT SUM(i.montant) as total, COUNT(*) as nombre
    FROM inscriptions i
    WHERE i.statut IN ('confirme', 'present') AND YEAR(i.date_paiement) = ?");
$stmt->execute([$annee]);
$inscriptions = $stmt->fetch();

// Détail par événement
$stmt = $pdo->prepare("SELECT e.titre, e.date_debut,
    SUM(i.montant) as recettes,
    (SELECT COALESCE(SUM(d.montant), 0) FROM depenses d WHERE d.evenement_id = e.id) as depenses
    FROM evenements e
    LEFT JOIN inscriptions i ON e.id = i.evenement_id AND i.statut IN ('confirme', 'present')
    WHERE YEAR(e.date_debut) = ?
    GROUP BY e.id
    ORDER BY e.date_debut");
$stmt->execute([$annee]);
$evenementsFinance = $stmt->fetchAll();

$totalRecettes = ($cotisations['total'] ?? 0) + ($inscriptions['total'] ?? 0);

// === DEPENSES ===

// Total dépenses
$stmt = $pdo->prepare("SELECT SUM(montant) as total FROM depenses WHERE YEAR(date_depense) = ?");
$stmt->execute([$annee]);
$totalDepenses = $stmt->fetch()['total'] ?? 0;

// Par catégorie
$stmt = $pdo->prepare("SELECT categorie, SUM(montant) as total
    FROM depenses
    WHERE YEAR(date_depense) = ?
    GROUP BY categorie
    ORDER BY total DESC");
$stmt->execute([$annee]);
$depensesParCategorie = $stmt->fetchAll();

// Par mois
$stmt = $pdo->prepare("SELECT MONTH(date_depense) as mois, SUM(montant) as total
    FROM depenses
    WHERE YEAR(date_depense) = ?
    GROUP BY MONTH(date_depense)
    ORDER BY mois");
$stmt->execute([$annee]);
$depensesParMois = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Recettes par mois (cotisations + inscriptions)
$stmt = $pdo->prepare("SELECT mois, SUM(total) as total FROM (
    SELECT MONTH(date_paiement) as mois, montant as total FROM cotisations WHERE statut = 'paye' AND YEAR(date_paiement) = ?
    UNION ALL
    SELECT MONTH(date_paiement) as mois, montant as total FROM inscriptions WHERE statut IN ('confirme', 'present') AND date_paiement IS NOT NULL AND YEAR(date_paiement) = ?
) t GROUP BY mois ORDER BY mois");
$stmt->execute([$annee, $annee]);
$recettesParMois = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Balance
$balance = $totalRecettes - $totalDepenses;

// Catégories
$categories = [
    'location' => ['label' => 'Location', 'icon' => '🏠'],
    'materiel' => ['label' => 'Matériel', 'icon' => '🔧'],
    'nourriture' => ['label' => 'Nourriture & Boissons', 'icon' => '🍽️'],
    'communication' => ['label' => 'Communication', 'icon' => '📢'],
    'artiste' => ['label' => 'Artiste / Intervenant', 'icon' => '🎤'],
    'transport' => ['label' => 'Transport', 'icon' => '🚗'],
    'autre' => ['label' => 'Autre', 'icon' => '📋']
];

$moisNoms = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

$pageTitle = 'Rapport Financier ' . $annee;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}
.year-selector {
    display: flex;
    align-items: center;
    gap: 8px;
}
.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.summary-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 24px;
    border: 1px solid var(--border);
    text-align: center;
}
.summary-card .amount {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 8px;
}
.summary-card .label {
    color: var(--gray);
    font-size: 14px;
}
.summary-card.recettes .amount { color: var(--success); }
.summary-card.depenses .amount { color: var(--danger); }
.summary-card.balance .amount { color: var(--primary); }
.summary-card.balance.negative .amount { color: var(--danger); }

.chart-container {
    background: var(--white);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    padding: 20px;
    margin-bottom: 20px;
}
.chart-container h3 {
    margin-bottom: 16px;
    font-size: 16px;
}

@media print {
    .no-print { display: none !important; }
    .card, .summary-card, .chart-container { break-inside: avoid; }
}
@media (max-width: 768px) {
    .summary-grid { grid-template-columns: 1fr; }
}
</style>

<div class="report-header no-print">
    <div class="year-selector">
        <label>Année :</label>
        <select onchange="window.location='?annee='+this.value">
            <?php foreach ($annees as $a): ?>
                <option value="<?php echo $a; ?>" <?php echo $a == $annee ? 'selected' : ''; ?>><?php echo $a; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="btn-group">
        <button onclick="window.print()" class="btn btn-secondary">Imprimer / PDF</button>
        <a href="depenses.php" class="btn btn-primary">Gérer les dépenses</a>
    </div>
</div>

<!-- Résumé -->
<div class="summary-grid">
    <div class="summary-card recettes">
        <div class="amount"><?php echo number_format($totalRecettes, 2, ',', ' '); ?> €</div>
        <div class="label">Total Recettes</div>
    </div>
    <div class="summary-card depenses">
        <div class="amount"><?php echo number_format($totalDepenses, 2, ',', ' '); ?> €</div>
        <div class="label">Total Dépenses</div>
    </div>
    <div class="summary-card balance <?php echo $balance < 0 ? 'negative' : ''; ?>">
        <div class="amount"><?php echo ($balance >= 0 ? '+' : '') . number_format($balance, 2, ',', ' '); ?> €</div>
        <div class="label"><?php echo $balance >= 0 ? 'Excédent' : 'Déficit'; ?></div>
    </div>
</div>

<!-- Graphique évolution mensuelle -->
<div class="chart-container">
    <h3>Évolution mensuelle <?php echo $annee; ?></h3>
    <canvas id="chartMensuel" height="100"></canvas>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
    <!-- Détail Recettes -->
    <div class="card">
        <div class="card-header">
            <h3>Détail des Recettes</h3>
        </div>
        <div class="card-body">
            <table>
                <tbody>
                    <tr>
                        <td>Cotisations</td>
                        <td><span class="badge badge-primary"><?php echo $cotisations['nombre'] ?? 0; ?></span></td>
                        <td class="text-right"><strong><?php echo number_format($cotisations['total'] ?? 0, 2, ',', ' '); ?> €</strong></td>
                    </tr>
                    <tr>
                        <td>Inscriptions événements</td>
                        <td><span class="badge badge-primary"><?php echo $inscriptions['nombre'] ?? 0; ?></span></td>
                        <td class="text-right"><strong><?php echo number_format($inscriptions['total'] ?? 0, 2, ',', ' '); ?> €</strong></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr style="background: var(--success-bg);">
                        <td colspan="2"><strong>Total Recettes</strong></td>
                        <td class="text-right"><strong style="color: var(--success);"><?php echo number_format($totalRecettes, 2, ',', ' '); ?> €</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Détail Dépenses par catégorie -->
    <div class="card">
        <div class="card-header">
            <h3>Dépenses par Catégorie</h3>
        </div>
        <div class="card-body">
            <?php if (empty($depensesParCategorie)): ?>
                <p class="text-gray text-center">Aucune dépense enregistrée</p>
            <?php else: ?>
                <table>
                    <tbody>
                        <?php foreach ($depensesParCategorie as $dep): ?>
                            <tr>
                                <td>
                                    <?php echo $categories[$dep['categorie']]['icon'] ?? ''; ?>
                                    <?php echo $categories[$dep['categorie']]['label'] ?? $dep['categorie']; ?>
                                </td>
                                <td class="text-right"><strong><?php echo number_format($dep['total'], 2, ',', ' '); ?> €</strong></td>
                                <td class="text-right text-gray">
                                    <?php echo $totalDepenses > 0 ? round(($dep['total'] / $totalDepenses) * 100) : 0; ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--danger-bg);">
                            <td><strong>Total Dépenses</strong></td>
                            <td class="text-right"><strong style="color: var(--danger);"><?php echo number_format($totalDepenses, 2, ',', ' '); ?> €</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bilan par événement -->
<?php if (!empty($evenementsFinance)): ?>
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>Bilan par Événement</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Événement</th>
                        <th>Date</th>
                        <th class="text-right">Recettes</th>
                        <th class="text-right">Dépenses</th>
                        <th class="text-right">Résultat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalEvRecettes = 0;
                    $totalEvDepenses = 0;
                    foreach ($evenementsFinance as $ev):
                        $resultat = ($ev['recettes'] ?? 0) - ($ev['depenses'] ?? 0);
                        $totalEvRecettes += ($ev['recettes'] ?? 0);
                        $totalEvDepenses += ($ev['depenses'] ?? 0);
                    ?>
                        <tr>
                            <td><strong><?php echo e($ev['titre']); ?></strong></td>
                            <td><?php echo formatDate($ev['date_debut']); ?></td>
                            <td class="text-right" style="color: var(--success);"><?php echo number_format($ev['recettes'] ?? 0, 2, ',', ' '); ?> €</td>
                            <td class="text-right" style="color: var(--danger);"><?php echo number_format($ev['depenses'] ?? 0, 2, ',', ' '); ?> €</td>
                            <td class="text-right">
                                <strong style="color: <?php echo $resultat >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                                    <?php echo ($resultat >= 0 ? '+' : '') . number_format($resultat, 2, ',', ' '); ?> €
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: var(--light);">
                        <td colspan="2"><strong>Total Événements</strong></td>
                        <td class="text-right"><strong style="color: var(--success);"><?php echo number_format($totalEvRecettes, 2, ',', ' '); ?> €</strong></td>
                        <td class="text-right"><strong style="color: var(--danger);"><?php echo number_format($totalEvDepenses, 2, ',', ' '); ?> €</strong></td>
                        <td class="text-right">
                            <?php $totalEvResultat = $totalEvRecettes - $totalEvDepenses; ?>
                            <strong style="color: <?php echo $totalEvResultat >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
                                <?php echo ($totalEvResultat >= 0 ? '+' : '') . number_format($totalEvResultat, 2, ',', ' '); ?> €
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('chartMensuel').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_slice($moisNoms, 1)); ?>,
        datasets: [
            {
                label: 'Recettes',
                data: <?php echo json_encode(array_map(function($m) use ($recettesParMois) {
                    return $recettesParMois[$m] ?? 0;
                }, range(1, 12))); ?>,
                backgroundColor: 'rgba(46, 125, 50, 0.7)',
                borderColor: 'rgba(46, 125, 50, 1)',
                borderWidth: 1
            },
            {
                label: 'Dépenses',
                data: <?php echo json_encode(array_map(function($m) use ($depensesParMois) {
                    return $depensesParMois[$m] ?? 0;
                }, range(1, 12))); ?>,
                backgroundColor: 'rgba(198, 40, 40, 0.7)',
                borderColor: 'rgba(198, 40, 40, 1)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value + ' €';
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.raw.toFixed(2) + ' €';
                    }
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

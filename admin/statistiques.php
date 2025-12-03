<?php
/**
 * Mini CRM - Statistiques avancées avec graphiques
 */

$pageTitle = 'Statistiques';
require_once __DIR__ . '/../includes/header.php';

requireLogin();
requireRole('admin');

// Année sélectionnée
$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');

// Statistiques générales
$stats = [];

// Membres actifs
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'membre' AND actif = 1");
$stats['membres_actifs'] = $stmt->fetchColumn();

// Membres inactifs
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'membre' AND actif = 0");
$stats['membres_inactifs'] = $stmt->fetchColumn();

// Cotisations à jour
$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM cotisations WHERE statut = 'paye' AND date_fin >= CURDATE()");
$stats['cotisations_jour'] = $stmt->fetchColumn();

// Événements cette année
$stmt = $pdo->prepare("SELECT COUNT(*) FROM evenements WHERE YEAR(date_debut) = ?");
$stmt->execute([$annee]);
$stats['evenements_annee'] = $stmt->fetchColumn();

// ===== Évolution des membres par mois =====
$stmt = $pdo->prepare("
    SELECT MONTH(created_at) as mois, COUNT(*) as count
    FROM users WHERE role = 'membre' AND YEAR(created_at) = ?
    GROUP BY MONTH(created_at)
    ORDER BY mois
");
$stmt->execute([$annee]);
$membresParMois = array_fill(1, 12, 0);
while ($row = $stmt->fetch()) {
    $membresParMois[$row['mois']] = (int)$row['count'];
}

// ===== Revenus par mois (cotisations) =====
$stmt = $pdo->prepare("
    SELECT MONTH(date_paiement) as mois, SUM(montant) as total
    FROM cotisations
    WHERE statut = 'paye' AND YEAR(date_paiement) = ?
    GROUP BY MONTH(date_paiement)
    ORDER BY mois
");
$stmt->execute([$annee]);
$revenusCotisationsParMois = array_fill(1, 12, 0);
while ($row = $stmt->fetch()) {
    $revenusCotisationsParMois[$row['mois']] = (float)$row['total'];
}

// ===== Revenus par mois (événements) =====
$stmt = $pdo->prepare("
    SELECT MONTH(i.created_at) as mois, SUM(i.montant) as total
    FROM inscriptions i
    JOIN evenements e ON i.evenement_id = e.id
    WHERE i.statut IN ('confirme', 'present') AND YEAR(i.created_at) = ?
    GROUP BY MONTH(i.created_at)
    ORDER BY mois
");
$stmt->execute([$annee]);
$revenusEvenementsParMois = array_fill(1, 12, 0);
while ($row = $stmt->fetch()) {
    $revenusEvenementsParMois[$row['mois']] = (float)$row['total'];
}

// ===== Membres par pays =====
$stmt = $pdo->query("
    SELECT COALESCE(pays_origine, 'Non renseigné') as pays, COUNT(*) as count
    FROM users WHERE role = 'membre' AND actif = 1
    GROUP BY pays_origine
    ORDER BY count DESC
    LIMIT 10
");
$membresParPays = $stmt->fetchAll();

// ===== Membres par nationalité =====
$stmt = $pdo->query("
    SELECT COALESCE(nationalite, 'Non renseignée') as nationalite, COUNT(*) as count
    FROM users WHERE role = 'membre' AND actif = 1
    GROUP BY nationalite
    ORDER BY count DESC
    LIMIT 10
");
$membresParNationalite = $stmt->fetchAll();

// ===== Types d'événements =====
$stmt = $pdo->prepare("
    SELECT type, COUNT(*) as count
    FROM evenements
    WHERE YEAR(date_debut) = ?
    GROUP BY type
    ORDER BY count DESC
");
$stmt->execute([$annee]);
$evenementsParType = $stmt->fetchAll();

// ===== Cotisations par type =====
$stmt = $pdo->prepare("
    SELECT ct.nom, COUNT(c.id) as count, SUM(c.montant) as total
    FROM cotisations c
    JOIN cotisation_types ct ON c.cotisation_type_id = ct.id
    WHERE c.statut = 'paye' AND YEAR(c.date_paiement) = ?
    GROUP BY ct.id, ct.nom
    ORDER BY total DESC
");
$stmt->execute([$annee]);
$cotisationsParType = $stmt->fetchAll();

// ===== Années disponibles =====
$stmt = $pdo->query("
    SELECT DISTINCT YEAR(created_at) as annee FROM users
    UNION SELECT DISTINCT YEAR(date_debut) FROM evenements
    UNION SELECT DISTINCT YEAR(date_paiement) FROM cotisations WHERE date_paiement IS NOT NULL
    ORDER BY annee DESC
");
$anneesDisponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($anneesDisponibles)) {
    $anneesDisponibles = [date('Y')];
}

$moisLabels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

$eventTypeLabels = [
    'spectacle' => 'Spectacle',
    'soiree' => 'Soirée',
    'tombola' => 'Tombola',
    'atelier' => 'Atelier',
    'reunion' => 'Réunion',
    'autre' => 'Autre'
];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-box {
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    text-align: center;
}
.stat-box .number {
    font-size: 36px;
    font-weight: 700;
    color: var(--primary);
}
.stat-box .label {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}
.chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 20px;
}
.chart-container {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.chart-container h3 {
    margin: 0 0 20px;
    font-size: 16px;
    color: #333;
}
.chart-wrapper {
    position: relative;
    height: 300px;
}
.year-selector {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 30px;
}
.year-selector select {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
}
</style>

<div class="year-selector">
    <label>Année :</label>
    <select onchange="window.location.href='?annee='+this.value">
        <?php foreach ($anneesDisponibles as $a): ?>
            <option value="<?php echo $a; ?>" <?php echo $a == $annee ? 'selected' : ''; ?>><?php echo $a; ?></option>
        <?php endforeach; ?>
    </select>
    <a href="?annee=<?php echo date('Y'); ?>" class="btn btn-secondary btn-sm">Cette année</a>
</div>

<!-- Résumé -->
<div class="stats-summary">
    <div class="stat-box">
        <div class="number"><?php echo $stats['membres_actifs']; ?></div>
        <div class="label">Membres actifs</div>
    </div>
    <div class="stat-box">
        <div class="number"><?php echo $stats['cotisations_jour']; ?></div>
        <div class="label">Cotisations à jour</div>
    </div>
    <div class="stat-box">
        <div class="number"><?php echo $stats['evenements_annee']; ?></div>
        <div class="label">Événements en <?php echo $annee; ?></div>
    </div>
    <div class="stat-box">
        <div class="number"><?php echo number_format(array_sum($revenusCotisationsParMois) + array_sum($revenusEvenementsParMois), 0, ',', ' '); ?> €</div>
        <div class="label">Revenus <?php echo $annee; ?></div>
    </div>
</div>

<div class="chart-grid">

    <!-- Évolution des membres -->
    <div class="chart-container">
        <h3>Nouveaux membres par mois (<?php echo $annee; ?>)</h3>
        <div class="chart-wrapper">
            <canvas id="chartMembres"></canvas>
        </div>
    </div>

    <!-- Revenus par mois -->
    <div class="chart-container">
        <h3>Revenus par mois (<?php echo $annee; ?>)</h3>
        <div class="chart-wrapper">
            <canvas id="chartRevenus"></canvas>
        </div>
    </div>

    <!-- Membres par pays -->
    <div class="chart-container">
        <h3>Membres par pays d'origine</h3>
        <div class="chart-wrapper">
            <canvas id="chartPays"></canvas>
        </div>
    </div>

    <!-- Types d'événements -->
    <div class="chart-container">
        <h3>Types d'événements (<?php echo $annee; ?>)</h3>
        <div class="chart-wrapper">
            <canvas id="chartEvenements"></canvas>
        </div>
    </div>

    <!-- Cotisations par type -->
    <div class="chart-container">
        <h3>Cotisations par type (<?php echo $annee; ?>)</h3>
        <div class="chart-wrapper">
            <canvas id="chartCotisations"></canvas>
        </div>
    </div>

    <!-- Nationalités -->
    <div class="chart-container">
        <h3>Membres par nationalité</h3>
        <div class="chart-wrapper">
            <canvas id="chartNationalites"></canvas>
        </div>
    </div>

</div>

<script>
const moisLabels = <?php echo json_encode($moisLabels); ?>;
const colors = ['#667eea', '#764ba2', '#4caf50', '#ff9800', '#2196f3', '#e91e63', '#9c27b0', '#00bcd4', '#8bc34a', '#ff5722'];

// Évolution des membres
new Chart(document.getElementById('chartMembres'), {
    type: 'bar',
    data: {
        labels: moisLabels,
        datasets: [{
            label: 'Nouveaux membres',
            data: <?php echo json_encode(array_values($membresParMois)); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.7)',
            borderColor: '#667eea',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Revenus par mois
new Chart(document.getElementById('chartRevenus'), {
    type: 'line',
    data: {
        labels: moisLabels,
        datasets: [{
            label: 'Cotisations',
            data: <?php echo json_encode(array_values($revenusCotisationsParMois)); ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            fill: true,
            tension: 0.4
        }, {
            label: 'Événements',
            data: <?php echo json_encode(array_values($revenusEvenementsParMois)); ?>,
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76, 175, 80, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) { return value + ' €'; }
                }
            }
        }
    }
});

// Membres par pays
new Chart(document.getElementById('chartPays'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($membresParPays, 'pays')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map('intval', array_column($membresParPays, 'count'))); ?>,
            backgroundColor: colors
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right' }
        }
    }
});

// Types d'événements
new Chart(document.getElementById('chartEvenements'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_map(function($e) use ($eventTypeLabels) {
            return $eventTypeLabels[$e['type']] ?? $e['type'];
        }, $evenementsParType)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map('intval', array_column($evenementsParType, 'count'))); ?>,
            backgroundColor: ['#e91e63', '#9c27b0', '#ff9800', '#4caf50', '#2196f3', '#607d8b']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right' }
        }
    }
});

// Cotisations par type
new Chart(document.getElementById('chartCotisations'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($cotisationsParType, 'nom')); ?>,
        datasets: [{
            label: 'Montant total',
            data: <?php echo json_encode(array_map('floatval', array_column($cotisationsParType, 'total'))); ?>,
            backgroundColor: colors
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                ticks: {
                    callback: function(value) { return value + ' €'; }
                }
            }
        }
    }
});

// Nationalités
new Chart(document.getElementById('chartNationalites'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($membresParNationalite, 'nationalite')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map('intval', array_column($membresParNationalite, 'count'))); ?>,
            backgroundColor: colors.slice().reverse()
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

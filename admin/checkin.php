<?php
/**
 * Mini CRM - Check-in / Scanner de billets
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('gestionnaire');

$evenement_id = (int)($_GET['evenement_id'] ?? 0);

// Traitement AJAX du check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'checkin') {
        $code = trim($_POST['code'] ?? '');

        if (empty($code)) {
            echo json_encode(['success' => false, 'message' => 'Code invalide']);
            exit;
        }

        // Rechercher le billet
        $sql = "SELECT i.*, e.titre as evenement_titre,
            COALESCE(CONCAT(u.prenom, ' ', u.nom), CONCAT(i.prenom_participant, ' ', i.nom_participant)) as participant_nom
            FROM inscriptions i
            JOIN evenements e ON i.evenement_id = e.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE i.ticket_code = ?";

        if ($evenement_id > 0) {
            $sql .= " AND i.evenement_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$code, $evenement_id]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$code]);
        }

        $ticket = $stmt->fetch();

        if (!$ticket) {
            echo json_encode(['success' => false, 'message' => 'Billet non trouvé', 'type' => 'error']);
            exit;
        }

        if ($ticket['statut'] === 'annule') {
            echo json_encode(['success' => false, 'message' => 'Billet annulé', 'type' => 'error', 'ticket' => $ticket]);
            exit;
        }

        if ($ticket['statut'] === 'present' || $ticket['checkin_at']) {
            echo json_encode([
                'success' => false,
                'message' => 'Déjà enregistré à ' . date('H:i', strtotime($ticket['checkin_at'])),
                'type' => 'warning',
                'ticket' => $ticket
            ]);
            exit;
        }

        if ($ticket['liste_attente']) {
            echo json_encode([
                'success' => false,
                'message' => 'Personne en liste d\'attente - Non confirmé',
                'type' => 'warning',
                'ticket' => $ticket
            ]);
            exit;
        }

        // Effectuer le check-in
        $stmt = $pdo->prepare("UPDATE inscriptions SET statut = 'present', checkin_at = NOW(), checkin_par = ? WHERE id = ?");
        $stmt->execute([getCurrentUser()['id'], $ticket['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Check-in réussi !',
            'type' => 'success',
            'ticket' => [
                'nom' => $ticket['participant_nom'],
                'places' => $ticket['nombre_places'],
                'evenement' => $ticket['evenement_titre']
            ]
        ]);
        exit;
    }
}

// Récupérer les événements actifs
$evenements = $pdo->query("SELECT id, titre, date_debut FROM evenements WHERE statut IN ('publie', 'complet') AND date_debut >= DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY date_debut")->fetchAll();

// Stats si événement sélectionné
$stats = null;
$checkins = [];
if ($evenement_id > 0) {
    $stmt = $pdo->prepare("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) as presents,
        SUM(CASE WHEN statut IN ('confirme', 'en_attente') AND liste_attente = 0 THEN 1 ELSE 0 END) as attendus,
        SUM(nombre_places) as total_places,
        SUM(CASE WHEN statut = 'present' THEN nombre_places ELSE 0 END) as places_presentes
        FROM inscriptions WHERE evenement_id = ? AND statut != 'annule'");
    $stmt->execute([$evenement_id]);
    $stats = $stmt->fetch();

    // Derniers check-ins
    $stmt = $pdo->prepare("SELECT i.*,
        COALESCE(CONCAT(u.prenom, ' ', u.nom), CONCAT(i.prenom_participant, ' ', i.nom_participant)) as participant_nom
        FROM inscriptions i
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.evenement_id = ? AND i.checkin_at IS NOT NULL
        ORDER BY i.checkin_at DESC LIMIT 20");
    $stmt->execute([$evenement_id]);
    $checkins = $stmt->fetchAll();
}

$pageTitle = 'Check-in';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.checkin-container {
    max-width: 600px;
    margin: 0 auto;
}
.scanner-box {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 30px;
    text-align: center;
    border: 2px solid var(--border);
    margin-bottom: 24px;
}
.scanner-input {
    width: 100%;
    padding: 16px;
    font-size: 20px;
    text-align: center;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-family: monospace;
    letter-spacing: 2px;
    text-transform: uppercase;
}
.scanner-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-bg);
}
.scanner-hint {
    margin-top: 12px;
    color: var(--gray);
    font-size: 14px;
}

.result-box {
    padding: 24px;
    border-radius: var(--radius-lg);
    text-align: center;
    margin-bottom: 24px;
    display: none;
}
.result-box.success { background: var(--success-bg); border: 2px solid var(--success); }
.result-box.error { background: var(--danger-bg); border: 2px solid var(--danger); }
.result-box.warning { background: var(--warning-bg); border: 2px solid var(--warning); }
.result-icon { font-size: 48px; margin-bottom: 12px; }
.result-message { font-size: 18px; font-weight: 600; }
.result-details { margin-top: 8px; color: var(--gray); }

.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 24px;
}
.stat-mini {
    background: var(--white);
    padding: 16px;
    border-radius: var(--radius);
    text-align: center;
    border: 1px solid var(--border);
}
.stat-mini .number { font-size: 28px; font-weight: 700; color: var(--primary); }
.stat-mini .label { font-size: 12px; color: var(--gray); margin-top: 4px; }

.checkin-list {
    max-height: 400px;
    overflow-y: auto;
}
.checkin-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid var(--border);
}
.checkin-item:last-child { border-bottom: none; }
.checkin-time { font-size: 12px; color: var(--gray); }
</style>

<div class="checkin-container">
    <!-- Sélection événement -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Événement</label>
                <select id="evenement-select" onchange="window.location='?evenement_id='+this.value">
                    <option value="">-- Sélectionner un événement --</option>
                    <?php foreach ($evenements as $ev): ?>
                        <option value="<?php echo $ev['id']; ?>" <?php echo $evenement_id == $ev['id'] ? 'selected' : ''; ?>>
                            <?php echo e($ev['titre']); ?> - <?php echo formatDate($ev['date_debut']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <?php if ($evenement_id > 0): ?>
        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number"><?php echo $stats['presents'] ?? 0; ?></div>
                <div class="label">Présents</div>
            </div>
            <div class="stat-mini">
                <div class="number"><?php echo $stats['attendus'] ?? 0; ?></div>
                <div class="label">Attendus</div>
            </div>
            <div class="stat-mini">
                <div class="number" style="color: var(--success);">
                    <?php echo ($stats['attendus'] ?? 0) > 0 ? round((($stats['presents'] ?? 0) / $stats['attendus']) * 100) : 0; ?>%
                </div>
                <div class="label">Taux présence</div>
            </div>
        </div>

        <!-- Scanner -->
        <div class="scanner-box">
            <h3 style="margin-bottom: 16px;">Scanner un billet</h3>
            <input type="text" id="code-input" class="scanner-input" placeholder="CODE DU BILLET" autofocus>
            <p class="scanner-hint">Scannez le QR code ou entrez le code manuellement</p>
        </div>

        <!-- Résultat -->
        <div id="result-box" class="result-box">
            <div class="result-icon" id="result-icon"></div>
            <div class="result-message" id="result-message"></div>
            <div class="result-details" id="result-details"></div>
        </div>

        <!-- Derniers check-ins -->
        <div class="card">
            <div class="card-header">
                <h3>Derniers check-ins</h3>
            </div>
            <div class="card-body checkin-list" id="checkin-list">
                <?php if (empty($checkins)): ?>
                    <p class="text-center text-gray">Aucun check-in pour le moment</p>
                <?php else: ?>
                    <?php foreach ($checkins as $c): ?>
                        <div class="checkin-item">
                            <div>
                                <strong><?php echo e($c['participant_nom']); ?></strong>
                                <span class="badge badge-primary"><?php echo $c['nombre_places']; ?> place(s)</span>
                            </div>
                            <div class="checkin-time"><?php echo date('H:i', strtotime($c['checkin_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">📱</div>
                    <h3>Sélectionnez un événement</h3>
                    <p>Choisissez un événement pour commencer le check-in</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const input = document.getElementById('code-input');
const resultBox = document.getElementById('result-box');
let timeout = null;

input?.addEventListener('input', function() {
    clearTimeout(timeout);
    const code = this.value.trim();

    if (code.length >= 8) {
        timeout = setTimeout(() => processCode(code), 300);
    }
});

input?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        processCode(this.value.trim());
    }
});

function processCode(code) {
    if (!code) return;

    fetch('checkin.php?evenement_id=<?php echo $evenement_id; ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=checkin&code=' + encodeURIComponent(code)
    })
    .then(r => r.json())
    .then(data => {
        showResult(data);
        input.value = '';
        input.focus();

        if (data.success) {
            // Recharger après 2 secondes pour mettre à jour les stats
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(err => {
        showResult({success: false, message: 'Erreur de connexion', type: 'error'});
    });
}

function showResult(data) {
    resultBox.style.display = 'block';
    resultBox.className = 'result-box ' + (data.type || (data.success ? 'success' : 'error'));

    const icons = {success: '✅', error: '❌', warning: '⚠️'};
    document.getElementById('result-icon').textContent = icons[data.type] || icons.error;
    document.getElementById('result-message').textContent = data.message;

    let details = '';
    if (data.ticket) {
        details = data.ticket.nom || '';
        if (data.ticket.places > 1) details += ' (' + data.ticket.places + ' places)';
    }
    document.getElementById('result-details').textContent = details;

    // Masquer après 3 secondes
    setTimeout(() => {
        resultBox.style.display = 'none';
    }, 3000);
}

// Focus auto sur le champ
input?.focus();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

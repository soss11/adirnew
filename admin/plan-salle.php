<?php
/**
 * Mini CRM - Gestion des plans de salle
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('gestionnaire');

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? '';
$eventId = (int)($_GET['event_id'] ?? 0);
$planId = (int)($_GET['plan_id'] ?? 0);

// Supprimer un plan
if ($action === 'delete' && $planId > 0) {
    $stmt = $pdo->prepare("SELECT evenement_id FROM event_plans_salle WHERE id = ?");
    $stmt->execute([$planId]);
    $p = $stmt->fetch();

    $stmt = $pdo->prepare("DELETE FROM event_plans_salle WHERE id = ?");
    $stmt->execute([$planId]);
    setFlashMessage('success', 'Plan de salle supprimé.');
    header('Location: plan-salle.php?event_id=' . ($p['evenement_id'] ?? 0));
    exit;
}

// Créer/modifier un plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $eventId = (int)$_POST['evenement_id'];
    $nom = trim($_POST['nom'] ?? 'Plan principal');
    $nbRangees = (int)($_POST['nb_rangees'] ?? 10);
    $placesParRangee = (int)($_POST['places_par_rangee'] ?? 20);

    if ($editId > 0) {
        $stmt = $pdo->prepare("UPDATE event_plans_salle SET nom = ?, nb_rangees = ?, places_par_rangee = ? WHERE id = ?");
        $stmt->execute([$nom, $nbRangees, $placesParRangee, $editId]);
        $planId = $editId;
    } else {
        $stmt = $pdo->prepare("INSERT INTO event_plans_salle (evenement_id, nom, nb_rangees, places_par_rangee, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$eventId, $nom, $nbRangees, $placesParRangee]);
        $planId = $pdo->lastInsertId();

        // Générer les places
        for ($r = 1; $r <= $nbRangees; $r++) {
            $rangee = chr(64 + $r); // A, B, C...
            for ($n = 1; $n <= $placesParRangee; $n++) {
                $stmt = $pdo->prepare("INSERT INTO event_places (plan_id, rangee, numero, categorie) VALUES (?, ?, ?, 'standard')");
                $stmt->execute([$planId, $rangee, $n]);
            }
        }
    }

    setFlashMessage('success', 'Plan de salle enregistré.');
    header('Location: plan-salle.php?action=edit&plan_id=' . $planId);
    exit;
}

// Modifier une place
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_place'])) {
    $placeId = (int)$_POST['place_id'];
    $categorie = $_POST['categorie'];
    $planId = (int)$_POST['plan_id'];

    $stmt = $pdo->prepare("UPDATE event_places SET categorie = ? WHERE id = ?");
    $stmt->execute([$categorie, $placeId]);

    header('Location: plan-salle.php?action=edit&plan_id=' . $planId);
    exit;
}

// Modifier plusieurs places
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    $placeIds = $_POST['place_ids'] ?? [];
    $categorie = $_POST['bulk_categorie'];
    $planId = (int)$_POST['plan_id'];

    if (!empty($placeIds)) {
        $placeholders = implode(',', array_fill(0, count($placeIds), '?'));
        $stmt = $pdo->prepare("UPDATE event_places SET categorie = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$categorie], $placeIds));
    }

    setFlashMessage('success', count($placeIds) . ' places mises à jour.');
    header('Location: plan-salle.php?action=edit&plan_id=' . $planId);
    exit;
}

$pageTitle = 'Plans de salle';
require_once __DIR__ . '/../includes/header.php';

// Récupérer les événements avec plans
$evenements = $pdo->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM event_plans_salle p WHERE p.evenement_id = e.id) as nb_plans,
           (SELECT SUM(ep.nb_rangees * ep.places_par_rangee) FROM event_plans_salle ep WHERE ep.evenement_id = e.id) as total_places
    FROM evenements e
    WHERE e.statut IN ('brouillon', 'publie', 'complet')
    ORDER BY e.date_debut DESC
")->fetchAll();

// Détails du plan
$plan = null;
$places = [];
if ($action === 'edit' && $planId > 0) {
    $stmt = $pdo->prepare("SELECT p.*, e.titre as event_titre FROM event_plans_salle p JOIN evenements e ON p.evenement_id = e.id WHERE p.id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    if ($plan) {
        $stmt = $pdo->prepare("SELECT ep.*, i.nom_participant, i.prenom_participant, u.nom as user_nom, u.prenom as user_prenom
                               FROM event_places ep
                               LEFT JOIN inscriptions i ON ep.inscription_id = i.id
                               LEFT JOIN users u ON ep.reserve_par = u.id
                               WHERE ep.plan_id = ?
                               ORDER BY ep.rangee, ep.numero");
        $stmt->execute([$planId]);
        $places = $stmt->fetchAll();
    }
}

// Événement sélectionné
$selectedEvent = null;
$plans = [];
if ($eventId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt->execute([$eventId]);
    $selectedEvent = $stmt->fetch();

    if ($selectedEvent) {
        $stmt = $pdo->prepare("SELECT p.*,
                               (SELECT COUNT(*) FROM event_places ep WHERE ep.plan_id = p.id AND ep.inscription_id IS NOT NULL) as places_reservees
                               FROM event_plans_salle p WHERE p.evenement_id = ?");
        $stmt->execute([$eventId]);
        $plans = $stmt->fetchAll();
    }
}

$categories = [
    'standard' => ['label' => 'Standard', 'color' => '#3b82f6'],
    'vip' => ['label' => 'VIP', 'color' => '#f59e0b'],
    'pmr' => ['label' => 'PMR', 'color' => '#10b981'],
    'bloque' => ['label' => 'Bloqué', 'color' => '#6b7280']
];
?>

<style>
.plan-grid {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 30px;
    background: #f9fafb;
    border-radius: 12px;
    overflow-x: auto;
}
.scene {
    background: linear-gradient(135deg, #1a56db, #7c3aed);
    color: #fff;
    padding: 15px 60px;
    border-radius: 8px 8px 0 0;
    font-weight: 600;
    margin-bottom: 20px;
}
.row {
    display: flex;
    align-items: center;
    gap: 5px;
}
.row-label {
    width: 30px;
    text-align: center;
    font-weight: 600;
    color: #666;
}
.seat {
    width: 28px;
    height: 28px;
    border-radius: 6px 6px 8px 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
    color: #fff;
}
.seat:hover {
    transform: scale(1.1);
    z-index: 10;
}
.seat.selected {
    border-color: #000;
    box-shadow: 0 0 0 2px #fff, 0 0 0 4px #000;
}
.seat.standard { background: #3b82f6; }
.seat.vip { background: #f59e0b; }
.seat.pmr { background: #10b981; }
.seat.bloque { background: #6b7280; }
.seat.reserved {
    background: #ef4444 !important;
    cursor: not-allowed;
}
.legend {
    display: flex;
    gap: 20px;
    margin-top: 20px;
    flex-wrap: wrap;
    justify-content: center;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}
.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}
.event-select-card {
    padding: 15px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}
.event-select-card:hover, .event-select-card.active {
    border-color: var(--primary);
    background: #f0f7ff;
}
.toolbar {
    display: flex;
    gap: 10px;
    padding: 15px;
    background: #fff;
    border-radius: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
}
</style>

<?php if ($action === 'edit' && $plan): ?>
<!-- Éditeur de plan -->
<div class="card">
    <div class="card-header">
        <h3><?php echo e($plan['event_titre']); ?> - <?php echo e($plan['nom']); ?></h3>
        <a href="plan-salle.php?event_id=<?php echo $plan['evenement_id']; ?>" class="btn btn-sm btn-secondary">Retour</a>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <span>Sélection :</span>
            <button onclick="selectAll()" class="btn btn-sm btn-secondary">Tout</button>
            <button onclick="selectNone()" class="btn btn-sm btn-secondary">Aucun</button>
            <span style="margin-left: 20px;">Appliquer :</span>
            <form method="POST" style="display: flex; gap: 10px;" id="bulkForm">
                <input type="hidden" name="bulk_update" value="1">
                <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                <div id="selectedPlacesInputs"></div>
                <select name="bulk_categorie" class="btn btn-sm">
                    <?php foreach ($categories as $k => $v): ?>
                        <option value="<?php echo $k; ?>"><?php echo $v['label']; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Appliquer</button>
            </form>
        </div>

        <div class="plan-grid">
            <div class="scene">SCÈNE</div>

            <?php
            $placesByRow = [];
            foreach ($places as $p) {
                $placesByRow[$p['rangee']][] = $p;
            }

            foreach ($placesByRow as $rangee => $rowPlaces):
            ?>
                <div class="row">
                    <span class="row-label"><?php echo $rangee; ?></span>
                    <?php foreach ($rowPlaces as $place):
                        $isReserved = !empty($place['inscription_id']);
                        $reserveName = $isReserved ? ($place['prenom_participant'] ?? $place['user_prenom']) . ' ' . ($place['nom_participant'] ?? $place['user_nom']) : '';
                    ?>
                        <div class="seat <?php echo $place['categorie']; ?> <?php echo $isReserved ? 'reserved' : ''; ?>"
                             data-id="<?php echo $place['id']; ?>"
                             data-row="<?php echo $place['rangee']; ?>"
                             data-num="<?php echo $place['numero']; ?>"
                             onclick="<?php echo $isReserved ? '' : 'toggleSeat(this)'; ?>"
                             title="<?php echo $place['rangee'] . $place['numero']; ?><?php echo $isReserved ? ' - ' . e($reserveName) : ''; ?>">
                            <?php echo $place['numero']; ?>
                        </div>
                    <?php endforeach; ?>
                    <span class="row-label"><?php echo $rangee; ?></span>
                </div>
            <?php endforeach; ?>

            <div class="legend">
                <?php foreach ($categories as $k => $v): ?>
                    <div class="legend-item">
                        <div class="legend-color" style="background: <?php echo $v['color']; ?>;"></div>
                        <span><?php echo $v['label']; ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ef4444;"></div>
                    <span>Réservé</span>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: center; color: #666;">
            <?php
            $stats = [
                'total' => count($places),
                'reserved' => count(array_filter($places, fn($p) => $p['inscription_id'])),
                'vip' => count(array_filter($places, fn($p) => $p['categorie'] === 'vip')),
                'pmr' => count(array_filter($places, fn($p) => $p['categorie'] === 'pmr')),
                'bloque' => count(array_filter($places, fn($p) => $p['categorie'] === 'bloque'))
            ];
            ?>
            <strong><?php echo $stats['total']; ?></strong> places au total |
            <strong style="color: #ef4444;"><?php echo $stats['reserved']; ?></strong> réservées |
            <strong style="color: #f59e0b;"><?php echo $stats['vip']; ?></strong> VIP |
            <strong style="color: #10b981;"><?php echo $stats['pmr']; ?></strong> PMR |
            <strong style="color: #6b7280;"><?php echo $stats['bloque']; ?></strong> bloquées
        </div>
    </div>
</div>

<script>
var selectedSeats = [];

function toggleSeat(el) {
    var id = el.dataset.id;
    if (el.classList.contains('selected')) {
        el.classList.remove('selected');
        selectedSeats = selectedSeats.filter(s => s !== id);
    } else {
        el.classList.add('selected');
        selectedSeats.push(id);
    }
    updateForm();
}

function selectAll() {
    document.querySelectorAll('.seat:not(.reserved)').forEach(el => {
        el.classList.add('selected');
        if (!selectedSeats.includes(el.dataset.id)) {
            selectedSeats.push(el.dataset.id);
        }
    });
    updateForm();
}

function selectNone() {
    document.querySelectorAll('.seat').forEach(el => el.classList.remove('selected'));
    selectedSeats = [];
    updateForm();
}

function updateForm() {
    var container = document.getElementById('selectedPlacesInputs');
    container.innerHTML = '';
    selectedSeats.forEach(id => {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'place_ids[]';
        input.value = id;
        container.appendChild(input);
    });
}
</script>

<?php elseif ($selectedEvent): ?>
<!-- Plans d'un événement -->
<div class="card">
    <div class="card-header">
        <h3>Plans de salle - <?php echo e($selectedEvent['titre']); ?></h3>
        <div class="btn-group">
            <button onclick="openModal('createPlanModal')" class="btn btn-sm btn-primary">+ Nouveau plan</button>
            <a href="plan-salle.php" class="btn btn-sm btn-secondary">Retour</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($plans)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#127915;</div>
                <h3>Aucun plan de salle</h3>
                <p>Créez un plan pour permettre la réservation de places numérotées.</p>
                <button onclick="openModal('createPlanModal')" class="btn btn-primary">Créer un plan</button>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php foreach ($plans as $p): ?>
                    <div style="background: #f9fafb; border-radius: 12px; padding: 20px;">
                        <h4 style="margin: 0 0 10px;"><?php echo e($p['nom']); ?></h4>
                        <p style="color: #666; margin-bottom: 15px;">
                            <?php echo $p['nb_rangees']; ?> rangées × <?php echo $p['places_par_rangee']; ?> places =
                            <strong><?php echo $p['nb_rangees'] * $p['places_par_rangee']; ?></strong> places
                        </p>
                        <div style="margin-bottom: 15px;">
                            <div style="background: #e5e7eb; border-radius: 10px; height: 10px; overflow: hidden;">
                                <?php $pct = $p['places_reservees'] / ($p['nb_rangees'] * $p['places_par_rangee']) * 100; ?>
                                <div style="background: #ef4444; height: 100%; width: <?php echo $pct; ?>%;"></div>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                <?php echo $p['places_reservees']; ?> / <?php echo $p['nb_rangees'] * $p['places_par_rangee']; ?> réservées
                            </div>
                        </div>
                        <div class="btn-group">
                            <a href="?action=edit&plan_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary">Éditer</a>
                            <a href="?action=delete&plan_id=<?php echo $p['id']; ?>&event_id=<?php echo $selectedEvent['id']; ?>"
                               onclick="return confirm('Supprimer ce plan ?')" class="btn btn-sm" style="color:#dc2626;">Supprimer</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal créer plan -->
<div class="modal-overlay" id="createPlanModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouveau plan de salle</h3>
            <button class="modal-close" onclick="closeModal('createPlanModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="save_plan" value="1">
                <input type="hidden" name="evenement_id" value="<?php echo $selectedEvent['id']; ?>">

                <div class="form-group">
                    <label>Nom du plan</label>
                    <input type="text" name="nom" value="Plan principal" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre de rangées</label>
                        <input type="number" name="nb_rangees" value="10" min="1" max="26" required>
                    </div>
                    <div class="form-group">
                        <label>Places par rangée</label>
                        <input type="number" name="places_par_rangee" value="20" min="1" max="50" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createPlanModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- Liste des événements -->
<div class="card">
    <div class="card-header">
        <h3>Plans de salle</h3>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 20px;">Sélectionnez un événement pour gérer son plan de salle :</p>

        <?php foreach ($evenements as $e): ?>
            <a href="?event_id=<?php echo $e['id']; ?>" class="event-select-card" style="display: block; text-decoration: none; color: inherit;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong><?php echo e($e['titre']); ?></strong>
                        <div style="font-size: 13px; color: #666;">
                            <?php echo date('d/m/Y', strtotime($e['date_debut'])); ?> - <?php echo e($e['lieu']); ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <?php if ($e['nb_plans'] > 0): ?>
                            <span class="badge badge-success"><?php echo $e['nb_plans']; ?> plan(s)</span>
                            <div style="font-size: 12px; color: #666;"><?php echo $e['total_places']; ?> places</div>
                        <?php else: ?>
                            <span class="badge">Pas de plan</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

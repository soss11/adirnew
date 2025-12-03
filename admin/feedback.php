<?php
/**
 * Mini CRM - Gestion des feedbacks/sondages événements
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('gestionnaire');

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? '';
$eventId = (int)($_GET['event_id'] ?? 0);
$feedbackId = (int)($_GET['id'] ?? 0);

// Supprimer un feedback
if ($action === 'delete' && $feedbackId > 0) {
    $stmt = $pdo->prepare("SELECT evenement_id FROM event_feedback WHERE id = ?");
    $stmt->execute([$feedbackId]);
    $fb = $stmt->fetch();

    $stmt = $pdo->prepare("DELETE FROM event_feedback WHERE id = ?");
    $stmt->execute([$feedbackId]);
    setFlashMessage('success', 'Feedback supprimé.');
    header('Location: feedback.php?event_id=' . ($fb['evenement_id'] ?? 0));
    exit;
}

$pageTitle = 'Feedbacks & Sondages';
require_once __DIR__ . '/../includes/header.php';

// Récupérer les événements terminés
$evenements = $pdo->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM event_feedback f WHERE f.evenement_id = e.id) as nb_feedbacks,
           (SELECT AVG(note_globale) FROM event_feedback f WHERE f.evenement_id = e.id) as note_moyenne
    FROM evenements e
    WHERE e.statut = 'termine' OR e.date_debut < NOW()
    ORDER BY e.date_debut DESC
")->fetchAll();

// Si un événement est sélectionné
$feedbacks = [];
$selectedEvent = null;
$stats = null;
if ($eventId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt->execute([$eventId]);
    $selectedEvent = $stmt->fetch();

    if ($selectedEvent) {
        $stmt = $pdo->prepare("SELECT f.*, u.nom as membre_nom, u.prenom as membre_prenom
                               FROM event_feedback f
                               LEFT JOIN users u ON f.user_id = u.id
                               WHERE f.evenement_id = ?
                               ORDER BY f.created_at DESC");
        $stmt->execute([$eventId]);
        $feedbacks = $stmt->fetchAll();

        // Statistiques
        $stmt = $pdo->prepare("SELECT
            COUNT(*) as total,
            AVG(note_globale) as moy_globale,
            AVG(note_organisation) as moy_organisation,
            AVG(note_lieu) as moy_lieu,
            AVG(note_animation) as moy_animation,
            SUM(CASE WHEN recommanderait = 1 THEN 1 ELSE 0 END) as recommandent
        FROM event_feedback WHERE evenement_id = ?");
        $stmt->execute([$eventId]);
        $stats = $stmt->fetch();
    }
}

function renderStars($note) {
    $note = (int)$note;
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span style="color: ' . ($i <= $note ? '#ffc107' : '#ddd') . ';">&#9733;</span>';
    }
    return $html;
}

function renderAvgStars($avg) {
    if (!$avg) return '-';
    $rounded = round($avg, 1);
    return renderStars(round($avg)) . ' <span style="color:#666;">(' . $rounded . ')</span>';
}
?>

<style>
.event-list-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 10px;
    margin-bottom: 10px;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}
.event-list-item:hover, .event-list-item.active {
    background: #e8f0fe;
    border-left: 3px solid var(--primary);
}
.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}
.stat-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-card.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-card .value { font-size: 32px; font-weight: bold; }
.stat-card .label { font-size: 14px; opacity: 0.9; }
.feedback-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
}
.feedback-card .header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}
.feedback-card .notes {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 8px;
}
.feedback-card .notes .note-item {
    text-align: center;
}
.feedback-card .notes .note-item label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}
.feedback-card .comment {
    padding: 15px;
    background: #f5f5f5;
    border-radius: 8px;
    margin-top: 10px;
}
.feedback-card .comment strong {
    display: block;
    margin-bottom: 5px;
    color: #333;
}
</style>

<div class="card">
    <div class="card-header">
        <h3>Feedbacks & Sondages</h3>
        <?php if ($selectedEvent): ?>
            <a href="<?php echo getBaseUrl(); ?>/feedback-form.php?event_id=<?php echo $eventId; ?>" class="btn btn-sm btn-success" target="_blank">Lien du formulaire</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 30px;">
            <!-- Liste des événements -->
            <div>
                <h4 style="margin-bottom: 15px;">Événements terminés</h4>
                <?php foreach ($evenements as $event): ?>
                    <a href="?event_id=<?php echo $event['id']; ?>" class="event-list-item <?php echo $eventId == $event['id'] ? 'active' : ''; ?>">
                        <div style="flex: 1;">
                            <div style="font-weight: 500;"><?php echo e($event['titre']); ?></div>
                            <div style="font-size: 12px; color: #666;"><?php echo date('d/m/Y', strtotime($event['date_debut'])); ?></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 18px; color: #ffc107;"><?php echo $event['note_moyenne'] ? renderStars(round($event['note_moyenne'])) : ''; ?></div>
                            <div style="font-size: 12px; color: #666;"><?php echo $event['nb_feedbacks']; ?> avis</div>
                        </div>
                    </a>
                <?php endforeach; ?>

                <?php if (empty($evenements)): ?>
                    <p style="color: #666;">Aucun événement terminé.</p>
                <?php endif; ?>
            </div>

            <!-- Détails feedbacks -->
            <div>
                <?php if ($selectedEvent): ?>
                    <h4><?php echo e($selectedEvent['titre']); ?></h4>
                    <p style="color: #666; margin-bottom: 20px;"><?php echo date('d/m/Y', strtotime($selectedEvent['date_debut'])); ?></p>

                    <?php if ($stats && $stats['total'] > 0): ?>
                        <!-- Statistiques -->
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px;">
                            <div class="stat-card">
                                <div class="value"><?php echo $stats['total']; ?></div>
                                <div class="label">Réponses</div>
                            </div>
                            <div class="stat-card green">
                                <div class="value"><?php echo number_format($stats['moy_globale'], 1); ?>/5</div>
                                <div class="label">Note moyenne</div>
                            </div>
                            <div class="stat-card orange">
                                <div class="value"><?php echo round(($stats['recommandent'] / $stats['total']) * 100); ?>%</div>
                                <div class="label">Recommanderaient</div>
                            </div>
                            <div class="stat-card blue">
                                <div class="value"><?php echo number_format($stats['moy_organisation'], 1); ?>/5</div>
                                <div class="label">Organisation</div>
                            </div>
                        </div>

                        <!-- Notes moyennes détaillées -->
                        <div style="background: #f9f9f9; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                            <h5 style="margin: 0 0 15px;">Notes moyennes par critère</h5>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; text-align: center;">
                                <div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Note globale</div>
                                    <?php echo renderAvgStars($stats['moy_globale']); ?>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Organisation</div>
                                    <?php echo renderAvgStars($stats['moy_organisation']); ?>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Lieu</div>
                                    <?php echo renderAvgStars($stats['moy_lieu']); ?>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Animation</div>
                                    <?php echo renderAvgStars($stats['moy_animation']); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Liste des feedbacks -->
                        <h5 style="margin-bottom: 15px;">Tous les avis (<?php echo count($feedbacks); ?>)</h5>
                        <?php foreach ($feedbacks as $fb): ?>
                            <div class="feedback-card">
                                <div class="header">
                                    <div>
                                        <strong>
                                            <?php
                                            if ($fb['user_id'] && $fb['membre_prenom']) {
                                                echo e($fb['membre_prenom'] . ' ' . $fb['membre_nom']);
                                            } elseif ($fb['nom_participant']) {
                                                echo e($fb['nom_participant']);
                                            } else {
                                                echo 'Anonyme';
                                            }
                                            ?>
                                        </strong>
                                        <div style="font-size: 12px; color: #666;"><?php echo date('d/m/Y à H:i', strtotime($fb['created_at'])); ?></div>
                                    </div>
                                    <div>
                                        <span style="font-size: 20px;"><?php echo renderStars($fb['note_globale']); ?></span>
                                        <a href="?action=delete&id=<?php echo $fb['id']; ?>&event_id=<?php echo $eventId; ?>"
                                           onclick="return confirm('Supprimer cet avis ?')"
                                           style="margin-left: 10px; color: #dc2626; font-size: 12px;">Supprimer</a>
                                    </div>
                                </div>

                                <div class="notes">
                                    <div class="note-item">
                                        <label>Organisation</label>
                                        <?php echo renderStars($fb['note_organisation']); ?>
                                    </div>
                                    <div class="note-item">
                                        <label>Lieu</label>
                                        <?php echo renderStars($fb['note_lieu']); ?>
                                    </div>
                                    <div class="note-item">
                                        <label>Animation</label>
                                        <?php echo renderStars($fb['note_animation']); ?>
                                    </div>
                                    <div class="note-item">
                                        <label>Recommande</label>
                                        <span style="color: <?php echo $fb['recommanderait'] ? '#16a34a' : '#dc2626'; ?>; font-weight: 500;">
                                            <?php echo $fb['recommanderait'] ? 'Oui' : 'Non'; ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if ($fb['points_positifs']): ?>
                                    <div class="comment">
                                        <strong style="color: #16a34a;">Points positifs</strong>
                                        <?php echo nl2br(e($fb['points_positifs'])); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($fb['points_ameliorer']): ?>
                                    <div class="comment">
                                        <strong style="color: #dc2626;">Points à améliorer</strong>
                                        <?php echo nl2br(e($fb['points_ameliorer'])); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($fb['suggestions']): ?>
                                    <div class="comment">
                                        <strong style="color: #2563eb;">Suggestions</strong>
                                        <?php echo nl2br(e($fb['suggestions'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <div style="text-align: center; padding: 60px 20px; background: #f9f9f9; border-radius: 10px;">
                            <div style="font-size: 64px;">&#128172;</div>
                            <h4>Aucun feedback pour le moment</h4>
                            <p style="color: #666;">Partagez le lien du formulaire avec les participants :</p>
                            <div style="background: #fff; padding: 15px; border-radius: 8px; margin-top: 15px;">
                                <code id="feedbackLink"><?php echo getBaseUrl(); ?>/feedback-form.php?event_id=<?php echo $eventId; ?></code>
                                <button onclick="copyLink()" class="btn btn-sm btn-secondary" style="margin-left: 10px;">Copier</button>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: #666;">
                        <div style="font-size: 64px;">&#128172;</div>
                        <h4>Sélectionnez un événement</h4>
                        <p>Choisissez un événement terminé pour voir les feedbacks.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copyLink() {
    var link = document.getElementById('feedbackLink').textContent;
    navigator.clipboard.writeText(link).then(function() {
        alert('Lien copié !');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

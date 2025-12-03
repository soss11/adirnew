<?php
/**
 * Mini CRM - Gestion du covoiturage
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('gestionnaire');

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? '';
$eventId = (int)($_GET['event_id'] ?? 0);
$covoitId = (int)($_GET['id'] ?? 0);

// Supprimer une offre
if ($action === 'delete' && $covoitId > 0) {
    $stmt = $pdo->prepare("SELECT evenement_id FROM covoiturage WHERE id = ?");
    $stmt->execute([$covoitId]);
    $c = $stmt->fetch();

    $stmt = $pdo->prepare("DELETE FROM covoiturage WHERE id = ?");
    $stmt->execute([$covoitId]);
    setFlashMessage('success', 'Offre supprimée.');
    header('Location: covoiturage.php?event_id=' . ($c['evenement_id'] ?? 0));
    exit;
}

// Répondre à une demande
if ($action === 'respond' && isset($_GET['reservation_id'])) {
    $resId = (int)$_GET['reservation_id'];
    $statut = $_GET['statut'] ?? '';

    if (in_array($statut, ['accepte', 'refuse'])) {
        $stmt = $pdo->prepare("UPDATE covoiturage_reservations SET statut = ?, reponse_at = NOW() WHERE id = ?");
        $stmt->execute([$statut, $resId]);

        // Mettre à jour les places disponibles si accepté
        if ($statut === 'accepte') {
            $stmt = $pdo->prepare("SELECT covoiturage_id, nb_places FROM covoiturage_reservations WHERE id = ?");
            $stmt->execute([$resId]);
            $res = $stmt->fetch();
            if ($res) {
                $stmt = $pdo->prepare("UPDATE covoiturage SET places_disponibles = places_disponibles - ? WHERE id = ?");
                $stmt->execute([$res['nb_places'], $res['covoiturage_id']]);
            }
        }

        setFlashMessage('success', 'Demande ' . ($statut === 'accepte' ? 'acceptée' : 'refusée') . '.');
    }
    header('Location: covoiturage.php?event_id=' . $eventId);
    exit;
}

$pageTitle = 'Covoiturage';
require_once __DIR__ . '/../includes/header.php';

// Événements à venir
$evenements = $pdo->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM covoiturage c WHERE c.evenement_id = e.id AND c.role = 'conducteur') as nb_conducteurs,
           (SELECT COUNT(*) FROM covoiturage c WHERE c.evenement_id = e.id AND c.role = 'passager') as nb_passagers,
           (SELECT SUM(places_disponibles) FROM covoiturage c WHERE c.evenement_id = e.id AND c.role = 'conducteur') as places_dispo
    FROM evenements e
    WHERE e.date_debut >= NOW() AND e.statut IN ('publie', 'complet')
    ORDER BY e.date_debut
")->fetchAll();

// Détails d'un événement
$selectedEvent = null;
$conducteurs = [];
$passagers = [];
$demandes = [];

if ($eventId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt->execute([$eventId]);
    $selectedEvent = $stmt->fetch();

    if ($selectedEvent) {
        // Conducteurs
        $stmt = $pdo->prepare("SELECT c.*, u.nom, u.prenom, u.email
                               FROM covoiturage c
                               JOIN users u ON c.user_id = u.id
                               WHERE c.evenement_id = ? AND c.role = 'conducteur' AND c.actif = 1
                               ORDER BY c.heure_depart");
        $stmt->execute([$eventId]);
        $conducteurs = $stmt->fetchAll();

        // Passagers recherchant
        $stmt = $pdo->prepare("SELECT c.*, u.nom, u.prenom, u.email
                               FROM covoiturage c
                               JOIN users u ON c.user_id = u.id
                               WHERE c.evenement_id = ? AND c.role = 'passager' AND c.actif = 1
                               ORDER BY c.created_at DESC");
        $stmt->execute([$eventId]);
        $passagers = $stmt->fetchAll();

        // Demandes en attente
        $stmt = $pdo->prepare("SELECT r.*, c.lieu_depart, c.heure_depart, u.nom, u.prenom, u.email,
                                      uc.nom as conducteur_nom, uc.prenom as conducteur_prenom
                               FROM covoiturage_reservations r
                               JOIN covoiturage c ON r.covoiturage_id = c.id
                               JOIN users u ON r.user_id = u.id
                               JOIN users uc ON c.user_id = uc.id
                               WHERE c.evenement_id = ?
                               ORDER BY r.created_at DESC");
        $stmt->execute([$eventId]);
        $demandes = $stmt->fetchAll();
    }
}
?>

<style>
.covoit-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
}
.covoit-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}
.covoit-route {
    display: flex;
    align-items: center;
    gap: 15px;
    color: #374151;
}
.covoit-route .icon {
    font-size: 24px;
}
.covoit-details {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    font-size: 14px;
    color: #6b7280;
}
.covoit-details span {
    display: flex;
    align-items: center;
    gap: 5px;
}
.places-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 600;
}
.places-badge.available { background: #dcfce7; color: #166534; }
.places-badge.full { background: #fee2e2; color: #991b1b; }
.demand-card {
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
}
.demand-card.accepted { background: #dcfce7; border-color: #86efac; }
.demand-card.refused { background: #fee2e2; border-color: #fca5a5; }
</style>

<?php if ($selectedEvent): ?>
<div class="card">
    <div class="card-header">
        <h3>Covoiturage - <?php echo e($selectedEvent['titre']); ?></h3>
        <a href="covoiturage.php" class="btn btn-sm btn-secondary">Retour</a>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px;">
            <div style="background: #dbeafe; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #1d4ed8;"><?php echo count($conducteurs); ?></div>
                <div style="color: #1e40af;">Conducteurs</div>
            </div>
            <div style="background: #dcfce7; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #166534;">
                    <?php echo array_sum(array_column($conducteurs, 'places_disponibles')); ?>
                </div>
                <div style="color: #15803d;">Places disponibles</div>
            </div>
            <div style="background: #fef3c7; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #b45309;"><?php echo count($passagers); ?></div>
                <div style="color: #92400e;">Passagers recherchant</div>
            </div>
        </div>

        <!-- Demandes en attente -->
        <?php
        $demandesEnAttente = array_filter($demandes, fn($d) => $d['statut'] === 'en_attente');
        if (!empty($demandesEnAttente)):
        ?>
        <div style="margin-bottom: 30px;">
            <h4 style="margin-bottom: 15px;">Demandes en attente (<?php echo count($demandesEnAttente); ?>)</h4>
            <?php foreach ($demandesEnAttente as $d): ?>
                <div class="demand-card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo e($d['prenom'] . ' ' . $d['nom']); ?></strong>
                            demande <?php echo $d['nb_places']; ?> place(s) à
                            <strong><?php echo e($d['conducteur_prenom'] . ' ' . $d['conducteur_nom']); ?></strong>
                            <div style="font-size: 13px; color: #666;">
                                Départ: <?php echo e($d['lieu_depart']); ?> à <?php echo $d['heure_depart'] ? date('H:i', strtotime($d['heure_depart'])) : '-'; ?>
                            </div>
                            <?php if ($d['message']): ?>
                                <div style="font-size: 13px; color: #666; margin-top: 5px;">
                                    <em>"<?php echo e($d['message']); ?>"</em>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="btn-group">
                            <a href="?action=respond&reservation_id=<?php echo $d['id']; ?>&statut=accepte&event_id=<?php echo $eventId; ?>"
                               class="btn btn-sm btn-success">Accepter</a>
                            <a href="?action=respond&reservation_id=<?php echo $d['id']; ?>&statut=refuse&event_id=<?php echo $eventId; ?>"
                               class="btn btn-sm" style="color: #dc2626;">Refuser</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Conducteurs -->
        <h4 style="margin-bottom: 15px;">Conducteurs proposant des places</h4>
        <?php if (empty($conducteurs)): ?>
            <p style="color: #666; margin-bottom: 30px;">Aucun conducteur n'a encore proposé de places.</p>
        <?php else: ?>
            <?php foreach ($conducteurs as $c): ?>
                <div class="covoit-card">
                    <div class="covoit-header">
                        <div class="covoit-route">
                            <span class="icon">&#128663;</span>
                            <div>
                                <strong><?php echo e($c['prenom'] . ' ' . $c['nom']); ?></strong>
                                <div style="font-size: 18px; margin-top: 5px;">
                                    <?php echo e($c['lieu_depart']); ?>
                                    <span style="color: #9ca3af;">→</span>
                                    <?php echo e($c['lieu_arrivee'] ?: $selectedEvent['lieu']); ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <span class="places-badge <?php echo $c['places_disponibles'] > 0 ? 'available' : 'full'; ?>">
                                <?php echo $c['places_disponibles']; ?> place(s)
                            </span>
                        </div>
                    </div>
                    <div class="covoit-details">
                        <?php if ($c['heure_depart']): ?>
                            <span>&#128337; Départ <?php echo date('H:i', strtotime($c['heure_depart'])); ?></span>
                        <?php endif; ?>
                        <?php if ($c['contribution'] > 0): ?>
                            <span>&#128176; <?php echo number_format($c['contribution'], 2); ?> € / pers.</span>
                        <?php else: ?>
                            <span>&#128176; Gratuit</span>
                        <?php endif; ?>
                        <span>&#128222; <?php echo e($c['telephone'] ?: $c['email']); ?></span>
                    </div>
                    <?php if ($c['commentaire']): ?>
                        <div style="margin-top: 10px; font-size: 14px; color: #666;">
                            <?php echo e($c['commentaire']); ?>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 15px;">
                        <a href="?action=delete&id=<?php echo $c['id']; ?>&event_id=<?php echo $eventId; ?>"
                           onclick="return confirm('Supprimer cette offre ?')"
                           style="color: #dc2626; font-size: 13px;">Supprimer</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Passagers -->
        <h4 style="margin: 30px 0 15px;">Passagers recherchant un covoiturage</h4>
        <?php if (empty($passagers)): ?>
            <p style="color: #666;">Aucun passager n'a encore posté de recherche.</p>
        <?php else: ?>
            <?php foreach ($passagers as $p): ?>
                <div class="covoit-card" style="background: #fefce8;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo e($p['prenom'] . ' ' . $p['nom']); ?></strong>
                            recherche un trajet depuis <strong><?php echo e($p['lieu_depart']); ?></strong>
                            <?php if ($p['commentaire']): ?>
                                <div style="font-size: 13px; color: #666; margin-top: 5px;">
                                    <?php echo e($p['commentaire']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="?action=delete&id=<?php echo $p['id']; ?>&event_id=<?php echo $eventId; ?>"
                           onclick="return confirm('Supprimer ?')" style="color: #dc2626;">Supprimer</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Liste des événements -->
<div class="card">
    <div class="card-header">
        <h3>Covoiturage par événement</h3>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 20px;">Sélectionnez un événement pour gérer le covoiturage :</p>

        <?php if (empty($evenements)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128663;</div>
                <h3>Aucun événement à venir</h3>
                <p>Le covoiturage est disponible pour les événements publiés à venir.</p>
            </div>
        <?php else: ?>
            <?php foreach ($evenements as $e): ?>
                <a href="?event_id=<?php echo $e['id']; ?>" style="display: block; text-decoration: none; color: inherit; padding: 15px; background: #f9fafb; border-radius: 10px; margin-bottom: 10px; transition: all 0.2s;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo e($e['titre']); ?></strong>
                            <div style="font-size: 13px; color: #666;">
                                <?php echo date('d/m/Y à H:i', strtotime($e['date_debut'])); ?> - <?php echo e($e['lieu']); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div>
                                <span class="badge" style="background: #dbeafe; color: #1d4ed8;"><?php echo $e['nb_conducteurs']; ?> conducteur(s)</span>
                                <span class="badge" style="background: #dcfce7; color: #166534;"><?php echo $e['places_dispo'] ?: 0; ?> places</span>
                            </div>
                            <?php if ($e['nb_passagers'] > 0): ?>
                                <div style="font-size: 12px; color: #b45309; margin-top: 5px;">
                                    <?php echo $e['nb_passagers']; ?> passager(s) recherchent
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

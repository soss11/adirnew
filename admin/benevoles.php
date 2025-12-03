<?php
/**
 * Mini CRM - Gestion des bénévoles
 */

$pageTitle = 'Bénévoles';
require_once __DIR__ . '/../includes/header.php';

requireLogin();
requireRole('gestionnaire');

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Roles de bénévoles prédéfinis
$rolesBenevoles = [
    'accueil' => 'Accueil',
    'billetterie' => 'Billetterie',
    'bar' => 'Bar/Restauration',
    'securite' => 'Sécurité',
    'technique' => 'Technique/Son/Lumière',
    'decoration' => 'Décoration',
    'animation' => 'Animation',
    'logistique' => 'Logistique',
    'nettoyage' => 'Nettoyage',
    'autre' => 'Autre'
];

$statutLabels = [
    'propose' => ['label' => 'Proposé', 'color' => '#9e9e9e'],
    'confirme' => ['label' => 'Confirmé', 'color' => '#4caf50'],
    'annule' => ['label' => 'Annulé', 'color' => '#f44336'],
    'present' => ['label' => 'Présent', 'color' => '#2196f3']
];

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'assign') {
        // Assigner un bénévole à un événement
        $evenement_id = (int)$_POST['evenement_id'];
        $user_id = (int)$_POST['user_id'];
        $role = $_POST['role_benevole'];
        $horaire_debut = $_POST['horaire_debut'] ?: null;
        $horaire_fin = $_POST['horaire_fin'] ?: null;
        $notes = $_POST['notes'] ?? '';

        try {
            $stmt = $pdo->prepare("INSERT INTO benevoles (user_id, evenement_id, role_benevole, horaire_debut, horaire_fin, notes, statut)
                                   VALUES (?, ?, ?, ?, ?, ?, 'propose')");
            $stmt->execute([$user_id, $evenement_id, $role, $horaire_debut, $horaire_fin, $notes]);

            // Marquer l'utilisateur comme bénévole
            $stmt = $pdo->prepare("UPDATE users SET est_benevole = 1 WHERE id = ?");
            $stmt->execute([$user_id]);

            setFlashMessage('success', 'Bénévole assigné avec succès.');
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                setFlashMessage('error', 'Ce bénévole est déjà assigné à ce rôle pour cet événement.');
            } else {
                setFlashMessage('error', 'Erreur lors de l\'assignation.');
            }
        }

        redirect('benevoles.php?action=event&id=' . $evenement_id);
    }

    if ($action === 'update_status') {
        $benevole_id = (int)$_POST['benevole_id'];
        $statut = $_POST['statut'];

        $stmt = $pdo->prepare("UPDATE benevoles SET statut = ? WHERE id = ?");
        $stmt->execute([$statut, $benevole_id]);

        setFlashMessage('success', 'Statut mis à jour.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'benevoles.php');
    }

    if ($action === 'delete') {
        $benevole_id = (int)$_POST['benevole_id'];

        $stmt = $pdo->prepare("DELETE FROM benevoles WHERE id = ?");
        $stmt->execute([$benevole_id]);

        setFlashMessage('success', 'Affectation supprimée.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'benevoles.php');
    }

    if ($action === 'toggle_benevole') {
        $user_id = (int)$_POST['user_id'];
        $est_benevole = (int)$_POST['est_benevole'];
        $competences = $_POST['competences'] ?? '';

        $stmt = $pdo->prepare("UPDATE users SET est_benevole = ?, competences_benevole = ? WHERE id = ?");
        $stmt->execute([$est_benevole, $competences, $user_id]);

        setFlashMessage('success', 'Informations bénévole mises à jour.');
        redirect('benevoles.php');
    }
}

// ========================================
// VUE : LISTE DES BÉNÉVOLES
// ========================================
if ($action === 'list'):

// Liste des bénévoles (membres marqués comme bénévoles)
$stmt = $pdo->query("SELECT u.*,
    (SELECT COUNT(*) FROM benevoles b WHERE b.user_id = u.id) as nb_participations,
    (SELECT COUNT(*) FROM benevoles b WHERE b.user_id = u.id AND b.statut = 'present') as nb_presences
    FROM users u
    WHERE u.role = 'membre' AND u.actif = 1 AND u.est_benevole = 1
    ORDER BY u.nom, u.prenom");
$benevoles = $stmt->fetchAll();

// Prochains événements avec besoins de bénévoles
$stmt = $pdo->query("SELECT e.*,
    (SELECT COUNT(*) FROM benevoles b WHERE b.evenement_id = e.id AND b.statut != 'annule') as nb_benevoles
    FROM evenements e
    WHERE e.date_debut >= NOW() AND e.statut IN ('publie', 'brouillon')
    ORDER BY e.date_debut ASC LIMIT 10");
$prochains_events = $stmt->fetchAll();

// Membres pouvant devenir bénévoles
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'membre' AND actif = 1 AND est_benevole = 0 ORDER BY nom, prenom");
$non_benevoles = $stmt->fetchAll();
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;">

    <!-- Liste des bénévoles -->
    <div class="card">
        <div class="card-header">
            <h3>Bénévoles actifs (<?php echo count($benevoles); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($benevoles)): ?>
                <p style="color:#666;text-align:center;padding:30px;">Aucun bénévole enregistré.</p>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($benevoles as $b): ?>
                        <div style="display:flex;align-items:center;gap:15px;padding:12px;background:#f9f9f9;border-radius:8px;">
                            <div style="width:45px;height:45px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                                <?php echo e(substr($b['prenom'] ?: $b['nom'], 0, 1)); ?>
                            </div>
                            <div style="flex:1;">
                                <strong><?php echo e($b['prenom'] . ' ' . $b['nom']); ?></strong>
                                <div style="font-size:12px;color:#666;">
                                    <?php echo $b['nb_participations']; ?> participation(s)
                                    • <?php echo $b['nb_presences']; ?> présence(s)
                                </div>
                                <?php if ($b['competences_benevole']): ?>
                                    <div style="font-size:11px;color:#888;margin-top:3px;">
                                        <?php echo e($b['competences_benevole']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form method="POST" action="?action=toggle_benevole" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="est_benevole" value="0">
                                <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Retirer le statut bénévole ?')">Retirer</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ajouter un bénévole -->
    <div class="card">
        <div class="card-header">
            <h3>Ajouter un bénévole</h3>
        </div>
        <div class="card-body">
            <?php if (empty($non_benevoles)): ?>
                <p style="color:#666;text-align:center;padding:20px;">Tous les membres sont déjà bénévoles.</p>
            <?php else: ?>
                <form method="POST" action="?action=toggle_benevole">
                    <div class="form-group">
                        <label>Membre</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">Sélectionner un membre</option>
                            <?php foreach ($non_benevoles as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo e($m['prenom'] . ' ' . $m['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Compétences / Disponibilités</label>
                        <textarea name="competences" class="form-control" rows="2" placeholder="Ex: Disponible weekends, expérience bar..."></textarea>
                    </div>
                    <input type="hidden" name="est_benevole" value="1">
                    <button type="submit" class="btn btn-primary">Ajouter comme bénévole</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Prochains événements -->
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Prochains événements - Bénévoles</h3>
    </div>
    <div class="card-body">
        <?php if (empty($prochains_events)): ?>
            <p style="color:#666;text-align:center;padding:20px;">Aucun événement à venir.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Événement</th>
                            <th>Date</th>
                            <th>Bénévoles</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prochains_events as $event): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($event['titre']); ?></strong>
                                    <?php if ($event['lieu']): ?>
                                        <div style="font-size:12px;color:#666;"><?php echo e($event['lieu']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDateTime($event['date_debut']); ?></td>
                                <td>
                                    <span class="badge <?php echo $event['nb_benevoles'] > 0 ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo $event['nb_benevoles']; ?> bénévole(s)
                                    </span>
                                </td>
                                <td>
                                    <a href="?action=event&id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">Gérer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// ========================================
// VUE : BÉNÉVOLES D'UN ÉVÉNEMENT
// ========================================
elseif ($action === 'event' && $id):

// Récupérer l'événement
$stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    setFlashMessage('error', 'Événement non trouvé.');
    redirect('benevoles.php');
}

// Bénévoles assignés
$stmt = $pdo->prepare("SELECT b.*, u.nom, u.prenom, u.email, u.telephone
    FROM benevoles b
    JOIN users u ON b.user_id = u.id
    WHERE b.evenement_id = ?
    ORDER BY b.role_benevole, u.nom");
$stmt->execute([$id]);
$benevoles_event = $stmt->fetchAll();

// Bénévoles disponibles (non encore assignés)
$stmt = $pdo->prepare("SELECT u.* FROM users u
    WHERE u.role = 'membre' AND u.actif = 1 AND u.est_benevole = 1
    AND u.id NOT IN (SELECT user_id FROM benevoles WHERE evenement_id = ?)
    ORDER BY u.nom, u.prenom");
$stmt->execute([$id]);
$benevoles_dispos = $stmt->fetchAll();

// Grouper par rôle
$benevolesParRole = [];
foreach ($benevoles_event as $b) {
    $role = $b['role_benevole'];
    if (!isset($benevolesParRole[$role])) {
        $benevolesParRole[$role] = [];
    }
    $benevolesParRole[$role][] = $b;
}
?>

<div class="card">
    <div class="card-header">
        <div>
            <h3><?php echo e($event['titre']); ?></h3>
            <p style="margin:5px 0 0;color:#666;"><?php echo formatDateTime($event['date_debut']); ?> • <?php echo e($event['lieu']); ?></p>
        </div>
        <a href="benevoles.php" class="btn btn-secondary">&larr; Retour</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 350px;gap:20px;margin-top:20px;">

    <!-- Liste des bénévoles par rôle -->
    <div>
        <?php if (empty($benevoles_event)): ?>
            <div class="card">
                <div class="card-body" style="text-align:center;padding:40px;">
                    <p style="color:#666;">Aucun bénévole assigné à cet événement.</p>
                    <p style="color:#888;font-size:13px;">Utilisez le formulaire à droite pour assigner des bénévoles.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($benevolesParRole as $role => $liste): ?>
                <div class="card" style="margin-bottom:15px;">
                    <div class="card-header">
                        <h4 style="margin:0;"><?php echo e($rolesBenevoles[$role] ?? $role); ?></h4>
                        <span class="badge badge-primary"><?php echo count($liste); ?></span>
                    </div>
                    <div class="card-body">
                        <?php foreach ($liste as $b): ?>
                            <div style="display:flex;align-items:center;gap:15px;padding:10px;background:#f9f9f9;border-radius:8px;margin-bottom:10px;">
                                <div style="flex:1;">
                                    <strong><?php echo e($b['prenom'] . ' ' . $b['nom']); ?></strong>
                                    <div style="font-size:12px;color:#666;">
                                        <?php if ($b['horaire_debut'] && $b['horaire_fin']): ?>
                                            <?php echo substr($b['horaire_debut'], 0, 5); ?> - <?php echo substr($b['horaire_fin'], 0, 5); ?>
                                        <?php endif; ?>
                                        <?php if ($b['telephone']): ?> • <?php echo e($b['telephone']); ?><?php endif; ?>
                                    </div>
                                    <?php if ($b['notes']): ?>
                                        <div style="font-size:11px;color:#888;margin-top:3px;"><?php echo e($b['notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;gap:5px;align-items:center;">
                                    <form method="POST" action="?action=update_status" style="display:inline;">
                                        <input type="hidden" name="benevole_id" value="<?php echo $b['id']; ?>">
                                        <select name="statut" class="form-control form-control-sm" onchange="this.form.submit()" style="width:auto;">
                                            <?php foreach ($statutLabels as $key => $val): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $b['statut'] === $key ? 'selected' : ''; ?>><?php echo $val['label']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <form method="POST" action="?action=delete" style="display:inline;">
                                        <input type="hidden" name="benevole_id" value="<?php echo $b['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette affectation ?')">×</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Formulaire d'assignation -->
    <div>
        <div class="card">
            <div class="card-header">
                <h4 style="margin:0;">Assigner un bénévole</h4>
            </div>
            <div class="card-body">
                <?php if (empty($benevoles_dispos)): ?>
                    <p style="color:#666;text-align:center;">Tous les bénévoles sont déjà assignés ou aucun bénévole n'est enregistré.</p>
                    <a href="benevoles.php" class="btn btn-secondary" style="width:100%;">Gérer les bénévoles</a>
                <?php else: ?>
                    <form method="POST" action="?action=assign">
                        <input type="hidden" name="evenement_id" value="<?php echo $id; ?>">

                        <div class="form-group">
                            <label>Bénévole</label>
                            <select name="user_id" class="form-control" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($benevoles_dispos as $b): ?>
                                    <option value="<?php echo $b['id']; ?>"><?php echo e($b['prenom'] . ' ' . $b['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Rôle</label>
                            <select name="role_benevole" class="form-control" required>
                                <?php foreach ($rolesBenevoles as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div class="form-group">
                                <label>Début</label>
                                <input type="time" name="horaire_debut" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Fin</label>
                                <input type="time" name="horaire_fin" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;">Assigner</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="card" style="margin-top:15px;">
            <div class="card-header">
                <h4 style="margin:0;">Résumé</h4>
            </div>
            <div class="card-body">
                <?php
                $statsBenevoles = [
                    'propose' => 0,
                    'confirme' => 0,
                    'present' => 0,
                    'annule' => 0
                ];
                foreach ($benevoles_event as $b) {
                    $statsBenevoles[$b['statut']]++;
                }
                ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($statutLabels as $key => $val): ?>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:<?php echo $val['color']; ?>;"><?php echo $val['label']; ?></span>
                            <strong><?php echo $statsBenevoles[$key]; ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

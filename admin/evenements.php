<?php
/**
 * Mini CRM - Gestion des événements
 */

$pageTitle = 'Événements';
require_once __DIR__ . '/../includes/header.php';

requireRole('gestionnaire');

$error = '';
$action = $_GET['action'] ?? '';
$eventId = (int)($_GET['id'] ?? 0);

$eventTypes = [
    'spectacle' => 'Spectacle',
    'soiree' => 'Soirée à thème',
    'tombola' => 'Tombola',
    'atelier' => 'Atelier',
    'reunion' => 'Réunion',
    'autre' => 'Autre'
];

$eventStatuts = [
    'brouillon' => 'Brouillon',
    'publie' => 'Publié',
    'complet' => 'Complet',
    'annule' => 'Annulé',
    'termine' => 'Terminé'
];

// Supprimer un événement
if ($action === 'delete' && $eventId > 0) {
    $stmt = $pdo->prepare("DELETE FROM evenements WHERE id = ?");
    $stmt->execute([$eventId]);
    setFlashMessage('success', 'Événement supprimé.');
    header('Location: evenements.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'autre';
    $date_debut = $_POST['date_debut'] ?? '';
    $heure_debut = $_POST['heure_debut'] ?? '18:00';
    $date_fin = $_POST['date_fin'] ?? '';
    $heure_fin = $_POST['heure_fin'] ?? '';
    $lieu = trim($_POST['lieu'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $places_max = (int)($_POST['places_max'] ?? 0);
    $prix_membre = (float)($_POST['prix_membre'] ?? 0);
    $prix_non_membre = (float)($_POST['prix_non_membre'] ?? 0);
    $statut = $_POST['statut'] ?? 'brouillon';

    if (empty($titre) || empty($date_debut)) {
        $error = 'Le titre et la date sont obligatoires.';
    } else {
        $datetime_debut = $date_debut . ' ' . $heure_debut . ':00';
        $datetime_fin = null;
        if ($date_fin) {
            $datetime_fin = $date_fin . ' ' . ($heure_fin ?: '23:59') . ':00';
        }

        // Upload affiche
        $afficheFilename = null;
        if (isset($_FILES['affiche']) && $_FILES['affiche']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $result = uploadImage($_FILES['affiche'], $uploadDir);
            if ($result['success']) {
                $afficheFilename = $result['filename'];
            }
        }

        try {
            if ($editId > 0) {
                $sql = "UPDATE evenements SET titre = ?, description = ?, type = ?, date_debut = ?,
                        date_fin = ?, lieu = ?, adresse = ?, places_max = ?, prix_membre = ?,
                        prix_non_membre = ?, statut = ?";
                $params = [$titre, $description, $type, $datetime_debut, $datetime_fin, $lieu,
                          $adresse, $places_max, $prix_membre, $prix_non_membre, $statut];

                if ($afficheFilename) {
                    $sql .= ", affiche = ?";
                    $params[] = $afficheFilename;
                }
                $sql .= " WHERE id = ?";
                $params[] = $editId;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                setFlashMessage('success', 'Événement modifié.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO evenements (titre, description, type, date_debut, date_fin,
                    lieu, adresse, places_max, prix_membre, prix_non_membre, affiche, statut, organisateur_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$titre, $description, $type, $datetime_debut, $datetime_fin, $lieu,
                    $adresse, $places_max, $prix_membre, $prix_non_membre, $afficheFilename, $statut, $currentUser['id']]);
                setFlashMessage('success', 'Événement créé.');
            }
            header('Location: evenements.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'enregistrement.';
        }
    }
}

// Ajouter une inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_inscription'])) {
    $inscEventId = (int)$_POST['evenement_id'];
    $inscUserId = (int)($_POST['user_id'] ?? 0) ?: null;
    $inscNom = trim($_POST['nom_participant'] ?? '');
    $inscPrenom = trim($_POST['prenom_participant'] ?? '');
    $inscEmail = trim($_POST['email_participant'] ?? '');
    $inscTel = trim($_POST['telephone_participant'] ?? '');
    $inscPlaces = (int)($_POST['nombre_places'] ?? 1);
    $inscMontant = (float)($_POST['montant'] ?? 0);
    $inscStatut = $_POST['insc_statut'] ?? 'en_attente';
    $inscPaiement = $_POST['mode_paiement'] ?? 'especes';

    $stmt = $pdo->prepare("INSERT INTO inscriptions (evenement_id, user_id, nom_participant, prenom_participant,
        email_participant, telephone_participant, nombre_places, montant, statut, mode_paiement, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$inscEventId, $inscUserId, $inscNom, $inscPrenom, $inscEmail, $inscTel,
        $inscPlaces, $inscMontant, $inscStatut, $inscPaiement]);

    setFlashMessage('success', 'Inscription ajoutée.');
    header("Location: evenements.php?action=view&id=$inscEventId");
    exit;
}

// Modifier statut inscription
if ($action === 'update_inscription' && isset($_GET['insc_id'])) {
    $inscId = (int)$_GET['insc_id'];
    $newStatus = $_GET['status'] ?? '';
    $eventIdRedir = (int)($_GET['event_id'] ?? 0);

    if (in_array($newStatus, ['en_attente', 'confirme', 'annule', 'present'])) {
        $stmt = $pdo->prepare("UPDATE inscriptions SET statut = ? WHERE id = ?");
        $stmt->execute([$newStatus, $inscId]);
    }
    header("Location: evenements.php?action=view&id=$eventIdRedir");
    exit;
}

// Récupérer l'événement à éditer
$editEvent = null;
if ($action === 'edit' && $eventId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt->execute([$eventId]);
    $editEvent = $stmt->fetch();
}

// Voir le détail d'un événement
$viewEvent = null;
$inscriptions = [];
if ($action === 'view' && $eventId > 0) {
    $stmt = $pdo->prepare("SELECT e.*, u.nom as org_nom, u.prenom as org_prenom
        FROM evenements e LEFT JOIN users u ON e.organisateur_id = u.id WHERE e.id = ?");
    $stmt->execute([$eventId]);
    $viewEvent = $stmt->fetch();

    if ($viewEvent) {
        $stmt = $pdo->prepare("SELECT i.*, u.nom as membre_nom, u.prenom as membre_prenom
            FROM inscriptions i LEFT JOIN users u ON i.user_id = u.id
            WHERE i.evenement_id = ? ORDER BY i.created_at DESC");
        $stmt->execute([$eventId]);
        $inscriptions = $stmt->fetchAll();
    }
}

// Liste des membres pour le formulaire d'inscription
$membres = $pdo->query("SELECT id, nom, prenom, email FROM users WHERE role = 'membre' AND actif = 1 ORDER BY nom")->fetchAll();

// Filtres
$filterType = $_GET['type'] ?? '';
$filterStatut = $_GET['statut'] ?? '';
$filterPeriode = $_GET['periode'] ?? 'a_venir';

// Liste des événements
$sql = "SELECT e.*, u.nom as org_nom, u.prenom as org_prenom,
        (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut != 'annule') as nb_inscrits,
        (SELECT SUM(i.nombre_places) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut != 'annule') as places_reservees
        FROM evenements e LEFT JOIN users u ON e.organisateur_id = u.id WHERE 1=1";
$params = [];

if ($filterType) {
    $sql .= " AND e.type = ?";
    $params[] = $filterType;
}
if ($filterStatut) {
    $sql .= " AND e.statut = ?";
    $params[] = $filterStatut;
}
if ($filterPeriode === 'a_venir') {
    $sql .= " AND e.date_debut >= NOW()";
} elseif ($filterPeriode === 'passe') {
    $sql .= " AND e.date_debut < NOW()";
}

$sql .= " ORDER BY e.date_debut " . ($filterPeriode === 'passe' ? 'DESC' : 'ASC');

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evenements = $stmt->fetchAll();

function getTypeBadge($type) {
    $colors = [
        'spectacle' => '#e91e63',
        'soiree' => '#9c27b0',
        'tombola' => '#ff9800',
        'atelier' => '#4caf50',
        'reunion' => '#2196f3',
        'autre' => '#607d8b'
    ];
    $color = $colors[$type] ?? '#607d8b';
    $labels = ['spectacle'=>'Spectacle','soiree'=>'Soirée','tombola'=>'Tombola','atelier'=>'Atelier','reunion'=>'Réunion','autre'=>'Autre'];
    return "<span class=\"badge\" style=\"background:".htmlspecialchars($color)."15;color:$color;\">".($labels[$type] ?? $type)."</span>";
}

function getStatutBadge($statut) {
    $badges = [
        'brouillon' => '<span class="badge" style="background:#eee;color:#666;">Brouillon</span>',
        'publie' => '<span class="badge badge-success">Publié</span>',
        'complet' => '<span class="badge" style="background:rgba(255,152,0,0.15);color:#e65100;">Complet</span>',
        'annule' => '<span class="badge badge-danger">Annulé</span>',
        'termine' => '<span class="badge" style="background:#eee;color:#666;">Terminé</span>'
    ];
    return $badges[$statut] ?? $statut;
}
?>

<?php if ($action === 'view' && $viewEvent): ?>
<!-- Vue détail événement -->
<div class="card">
    <div class="card-header">
        <h3><?php echo e($viewEvent['titre']); ?></h3>
        <div class="btn-group">
            <a href="?action=edit&id=<?php echo $viewEvent['id']; ?>" class="btn btn-sm btn-primary">Modifier</a>
            <a href="evenements.php" class="btn btn-sm btn-secondary">Retour</a>
        </div>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:<?php echo $viewEvent['affiche'] ? '200px 1fr' : '1fr'; ?>;gap:30px;">
            <?php if ($viewEvent['affiche']): ?>
                <img src="<?php echo getBaseUrl(); ?>/assets/uploads/<?php echo e($viewEvent['affiche']); ?>"
                     style="width:100%;border-radius:8px;">
            <?php endif; ?>
            <div>
                <div style="display:flex;gap:10px;margin-bottom:15px;">
                    <?php echo getTypeBadge($viewEvent['type']); ?>
                    <?php echo getStatutBadge($viewEvent['statut']); ?>
                </div>

                <table class="info-table">
                    <tr><td>Date</td><td>
                        <?php echo date('d/m/Y à H:i', strtotime($viewEvent['date_debut'])); ?>
                        <?php if ($viewEvent['date_fin']): ?>
                            - <?php echo date('H:i', strtotime($viewEvent['date_fin'])); ?>
                        <?php endif; ?>
                    </td></tr>
                    <tr><td>Lieu</td><td><?php echo e($viewEvent['lieu'] ?: '-'); ?></td></tr>
                    <?php if ($viewEvent['adresse']): ?>
                        <tr><td>Adresse</td><td><?php echo e($viewEvent['adresse']); ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Places</td><td>
                        <?php
                        $reservees = (int)($viewEvent['places_reservees'] ?? 0);
                        if ($viewEvent['places_max'] > 0) {
                            echo "$reservees / {$viewEvent['places_max']}";
                            $pct = round($reservees / $viewEvent['places_max'] * 100);
                            echo " <span style='color:#666;'>($pct%)</span>";
                        } else {
                            echo "$reservees (illimité)";
                        }
                        ?>
                    </td></tr>
                    <tr><td>Tarifs</td><td>
                        Membre : <?php echo number_format($viewEvent['prix_membre'], 2); ?> €
                        / Non-membre : <?php echo number_format($viewEvent['prix_non_membre'], 2); ?> €
                    </td></tr>
                    <tr><td>Organisateur</td><td><?php echo e(($viewEvent['org_prenom'] ?? '') . ' ' . ($viewEvent['org_nom'] ?? '-')); ?></td></tr>
                </table>

                <?php if ($viewEvent['description']): ?>
                    <div style="margin-top:15px;">
                        <strong>Description :</strong><br>
                        <?php echo nl2br(e($viewEvent['description'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Inscriptions -->
<div class="card">
    <div class="card-header">
        <h3>Inscriptions (<?php echo count($inscriptions); ?>)</h3>
        <button onclick="openModal('inscriptionModal')" class="btn btn-sm btn-primary">+ Ajouter</button>
    </div>
    <div class="card-body">
        <?php
        $totalMontant = array_sum(array_column(array_filter($inscriptions, fn($i) => $i['statut'] !== 'annule'), 'montant'));
        $totalPlaces = array_sum(array_column(array_filter($inscriptions, fn($i) => $i['statut'] !== 'annule'), 'nombre_places'));
        ?>
        <div style="display:flex;gap:20px;margin-bottom:20px;flex-wrap:wrap;">
            <div style="padding:15px;background:#e8f5e9;border-radius:8px;">
                <div style="font-size:24px;font-weight:bold;color:#2e7d32;"><?php echo $totalPlaces; ?></div>
                <div style="color:#666;">places réservées</div>
            </div>
            <div style="padding:15px;background:#e3f2fd;border-radius:8px;">
                <div style="font-size:24px;font-weight:bold;color:#1565c0;"><?php echo number_format($totalMontant, 2); ?> €</div>
                <div style="color:#666;">total encaissé</div>
            </div>
        </div>

        <?php if (empty($inscriptions)): ?>
            <p style="color:#666;">Aucune inscription pour le moment.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Participant</th>
                            <th>Contact</th>
                            <th>Places</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inscriptions as $insc): ?>
                            <tr>
                                <td>
                                    <?php
                                    $nom = $insc['membre_prenom'] ?? $insc['prenom_participant'];
                                    $prenom = $insc['membre_nom'] ?? $insc['nom_participant'];
                                    echo e("$nom $prenom");
                                    if ($insc['user_id']): ?>
                                        <span class="badge badge-membre" style="font-size:10px;margin-left:5px;">Membre</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:13px;">
                                    <?php echo e($insc['email_participant']); ?>
                                    <?php if ($insc['telephone_participant']): ?>
                                        <br><span style="color:#666;"><?php echo e($insc['telephone_participant']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $insc['nombre_places']; ?></td>
                                <td><?php echo number_format($insc['montant'], 2); ?> €</td>
                                <td>
                                    <?php
                                    $inscBadges = [
                                        'confirme' => '<span class="badge badge-success">Confirmé</span>',
                                        'en_attente' => '<span class="badge" style="background:rgba(255,152,0,0.15);color:#e65100;">En attente</span>',
                                        'present' => '<span class="badge badge-info">Présent</span>',
                                        'annule' => '<span class="badge badge-danger">Annulé</span>'
                                    ];
                                    echo $inscBadges[$insc['statut']] ?? $insc['statut'];
                                    ?>
                                </td>
                                <td>
                                    <select onchange="window.location.href='?action=update_inscription&insc_id=<?php echo $insc['id']; ?>&event_id=<?php echo $viewEvent['id']; ?>&status='+this.value"
                                            style="padding:5px;font-size:12px;">
                                        <option value="">Changer...</option>
                                        <option value="en_attente">En attente</option>
                                        <option value="confirme">Confirmé</option>
                                        <option value="present">Présent</option>
                                        <option value="annule">Annulé</option>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Inscription -->
<div class="modal-overlay" id="inscriptionModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouvelle inscription</h3>
            <button class="modal-close" onclick="closeModal('inscriptionModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="add_inscription" value="1">
                <input type="hidden" name="evenement_id" value="<?php echo $viewEvent['id']; ?>">

                <div class="form-group">
                    <label>Membre existant (optionnel)</label>
                    <select name="user_id" id="membre_select" onchange="fillMemberInfo()">
                        <option value="">-- Non-membre --</option>
                        <?php foreach ($membres as $m): ?>
                            <option value="<?php echo $m['id']; ?>" data-email="<?php echo e($m['email']); ?>">
                                <?php echo e($m['prenom'] . ' ' . $m['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom_participant" id="nom_participant" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom_participant" id="prenom_participant">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email_participant" id="email_participant">
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone_participant">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre de places</label>
                        <input type="number" name="nombre_places" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label>Montant (€)</label>
                        <input type="number" name="montant" step="0.01" value="<?php echo $viewEvent['prix_membre']; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Statut</label>
                        <select name="insc_statut">
                            <option value="confirme">Confirmé</option>
                            <option value="en_attente">En attente</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mode de paiement</label>
                        <select name="mode_paiement">
                            <option value="especes">Espèces</option>
                            <option value="cheque">Chèque</option>
                            <option value="carte">Carte</option>
                            <option value="virement">Virement</option>
                            <option value="gratuit">Gratuit</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('inscriptionModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<script>
function fillMemberInfo() {
    var select = document.getElementById('membre_select');
    var option = select.options[select.selectedIndex];
    if (option.value) {
        var parts = option.text.split(' ');
        document.getElementById('prenom_participant').value = parts[0] || '';
        document.getElementById('nom_participant').value = parts.slice(1).join(' ') || '';
        document.getElementById('email_participant').value = option.dataset.email || '';
    }
}
</script>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- Formulaire événement -->
<div class="card">
    <div class="card-header">
        <h3><?php echo $editEvent ? 'Modifier l\'événement' : 'Nouvel événement'; ?></h3>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="save_event" value="1">
            <input type="hidden" name="edit_id" value="<?php echo $editEvent ? $editEvent['id'] : 0; ?>">

            <div class="section-title" style="margin-top:0;">Informations générales</div>
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Titre *</label>
                    <input type="text" name="titre" value="<?php echo e($editEvent['titre'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type">
                        <?php foreach ($eventTypes as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($editEvent['type'] ?? '') === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"><?php echo e($editEvent['description'] ?? ''); ?></textarea>
            </div>

            <div class="section-title">Date et lieu</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Date de début *</label>
                    <input type="date" name="date_debut" value="<?php echo $editEvent ? date('Y-m-d', strtotime($editEvent['date_debut'])) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Heure de début</label>
                    <input type="time" name="heure_debut" value="<?php echo $editEvent ? date('H:i', strtotime($editEvent['date_debut'])) : '18:00'; ?>">
                </div>
                <div class="form-group">
                    <label>Date de fin</label>
                    <input type="date" name="date_fin" value="<?php echo $editEvent && $editEvent['date_fin'] ? date('Y-m-d', strtotime($editEvent['date_fin'])) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Heure de fin</label>
                    <input type="time" name="heure_fin" value="<?php echo $editEvent && $editEvent['date_fin'] ? date('H:i', strtotime($editEvent['date_fin'])) : ''; ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Lieu</label>
                    <input type="text" name="lieu" value="<?php echo e($editEvent['lieu'] ?? ''); ?>" placeholder="Salle des fêtes...">
                </div>
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" name="adresse" value="<?php echo e($editEvent['adresse'] ?? ''); ?>">
                </div>
            </div>

            <div class="section-title">Tarifs et places</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Nombre de places (0 = illimité)</label>
                    <input type="number" name="places_max" value="<?php echo $editEvent['places_max'] ?? 0; ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Prix membre (€)</label>
                    <input type="number" name="prix_membre" step="0.01" value="<?php echo $editEvent['prix_membre'] ?? 0; ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Prix non-membre (€)</label>
                    <input type="number" name="prix_non_membre" step="0.01" value="<?php echo $editEvent['prix_non_membre'] ?? 0; ?>" min="0">
                </div>
            </div>

            <div class="section-title">Publication</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Statut</label>
                    <select name="statut">
                        <?php foreach ($eventStatuts as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($editEvent['statut'] ?? 'brouillon') === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Affiche</label>
                    <input type="file" name="affiche" accept="image/*">
                    <?php if ($editEvent && $editEvent['affiche']): ?>
                        <p class="form-hint">Affiche actuelle : <?php echo e($editEvent['affiche']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top:30px;" class="btn-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="evenements.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- Liste des événements -->
<div class="card">
    <div class="card-header">
        <h3>Événements (<?php echo count($evenements); ?>)</h3>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Nouvel événement</a>
    </div>
    <div class="card-body">
        <!-- Filtres -->
        <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
            <select name="periode" style="width:auto;">
                <option value="a_venir" <?php echo $filterPeriode === 'a_venir' ? 'selected' : ''; ?>>À venir</option>
                <option value="passe" <?php echo $filterPeriode === 'passe' ? 'selected' : ''; ?>>Passés</option>
                <option value="tous" <?php echo $filterPeriode === 'tous' ? 'selected' : ''; ?>>Tous</option>
            </select>
            <select name="type" style="width:auto;">
                <option value="">Tous types</option>
                <?php foreach ($eventTypes as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $filterType === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="statut" style="width:auto;">
                <option value="">Tous statuts</option>
                <?php foreach ($eventStatuts as $k => $v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $filterStatut === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-secondary">Filtrer</button>
        </form>

        <?php if (empty($evenements)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#9734;</div>
                <h3>Aucun événement</h3>
                <p>Créez votre premier événement.</p>
            </div>
        <?php else: ?>
            <div style="display:grid;gap:20px;">
                <?php foreach ($evenements as $event): ?>
                    <div style="display:flex;gap:20px;padding:20px;background:#f9f9f9;border-radius:10px;">
                        <?php if ($event['affiche']): ?>
                            <img src="<?php echo getBaseUrl(); ?>/assets/uploads/<?php echo e($event['affiche']); ?>"
                                 style="width:120px;height:80px;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                            <div style="width:120px;height:80px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:30px;">
                                &#9734;
                            </div>
                        <?php endif; ?>
                        <div style="flex:1;">
                            <div style="display:flex;justify-content:space-between;align-items:start;">
                                <div>
                                    <h4 style="margin:0 0 5px;"><?php echo e($event['titre']); ?></h4>
                                    <div style="display:flex;gap:8px;margin-bottom:8px;">
                                        <?php echo getTypeBadge($event['type']); ?>
                                        <?php echo getStatutBadge($event['statut']); ?>
                                    </div>
                                </div>
                                <div class="btn-group">
                                    <a href="?action=view&id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">Voir</a>
                                    <a href="?action=edit&id=<?php echo $event['id']; ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                </div>
                            </div>
                            <div style="color:#666;font-size:14px;">
                                <strong><?php echo date('d/m/Y à H:i', strtotime($event['date_debut'])); ?></strong>
                                <?php if ($event['lieu']): ?> - <?php echo e($event['lieu']); ?><?php endif; ?>
                            </div>
                            <div style="margin-top:8px;font-size:13px;color:#888;">
                                <?php
                                $reservees = (int)($event['places_reservees'] ?? 0);
                                echo "$reservees inscrit(s)";
                                if ($event['places_max'] > 0) {
                                    echo " / {$event['places_max']} places";
                                }
                                ?>
                                <?php if ($event['prix_membre'] > 0): ?>
                                    | <?php echo number_format($event['prix_membre'], 2); ?> € (membre)
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<style>
.info-table { width: 100%; }
.info-table td { padding: 8px 0; border-bottom: 1px solid #eee; }
.info-table td:first-child { font-weight: 500; color: #666; width: 120px; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

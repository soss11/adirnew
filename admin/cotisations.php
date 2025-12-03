<?php
/**
 * Mini CRM - Gestion des cotisations
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('gestionnaire');

$error = '';
$action = $_GET['action'] ?? '';
$cotisationId = (int)($_GET['id'] ?? 0);

// Supprimer une cotisation
if ($action === 'delete' && $cotisationId > 0) {
    $stmt = $pdo->prepare("DELETE FROM cotisations WHERE id = ?");
    $stmt->execute([$cotisationId]);
    setFlashMessage('success', 'Cotisation supprimée.');
    header('Location: cotisations.php');
    exit;
}

// Marquer comme payé
if ($action === 'payer' && $cotisationId > 0) {
    $stmt = $pdo->prepare("UPDATE cotisations SET statut = 'paye', date_paiement = CURDATE() WHERE id = ?");
    $stmt->execute([$cotisationId]);
    setFlashMessage('success', 'Cotisation marquée comme payée.');
    header('Location: cotisations.php');
    exit;
}

// Supprimer un type
if ($action === 'delete_type' && isset($_GET['type_id'])) {
    $typeId = (int)$_GET['type_id'];
    $stmt = $pdo->prepare("UPDATE cotisation_types SET actif = 0 WHERE id = ?");
    $stmt->execute([$typeId]);
    setFlashMessage('success', 'Type de cotisation supprimé.');
    header('Location: cotisations.php?tab=types');
    exit;
}

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cotisation'])) {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    $typeId = (int)($_POST['cotisation_type_id'] ?? 0);
    $montant = (float)($_POST['montant'] ?? 0);
    $dateDebut = $_POST['date_debut'] ?? '';
    $dateFin = $_POST['date_fin'] ?? '';
    $modePaiement = $_POST['mode_paiement'] ?? 'especes';
    $statut = $_POST['statut'] ?? 'en_attente';
    $datePaiement = $_POST['date_paiement'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (!$userId || !$typeId || !$dateDebut || !$dateFin) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        try {
            if ($editId > 0) {
                $stmt = $pdo->prepare("UPDATE cotisations SET user_id = ?, cotisation_type_id = ?, montant = ?,
                    date_debut = ?, date_fin = ?, mode_paiement = ?, statut = ?, date_paiement = ?, notes = ?
                    WHERE id = ?");
                $stmt->execute([$userId, $typeId, $montant, $dateDebut, $dateFin, $modePaiement, $statut,
                    $datePaiement ?: null, $notes, $editId]);
                setFlashMessage('success', 'Cotisation modifiée.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO cotisations (user_id, cotisation_type_id, montant, date_debut,
                    date_fin, mode_paiement, statut, date_paiement, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$userId, $typeId, $montant, $dateDebut, $dateFin, $modePaiement, $statut,
                    $datePaiement ?: null, $notes]);
                setFlashMessage('success', 'Cotisation créée.');
            }
            header('Location: cotisations.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'enregistrement.';
        }
    }
}

// Gestion des types de cotisation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_type'])) {
    $typeEditId = (int)($_POST['type_edit_id'] ?? 0);
    $typeNom = trim($_POST['type_nom'] ?? '');
    $typeDesc = trim($_POST['type_description'] ?? '');
    $typeMontant = (float)($_POST['type_montant'] ?? 0);
    $typeDuree = (int)($_POST['type_duree'] ?? 12);

    if (!empty($typeNom) && $typeMontant > 0) {
        if ($typeEditId > 0) {
            $stmt = $pdo->prepare("UPDATE cotisation_types SET nom = ?, description = ?, montant = ?, duree_mois = ? WHERE id = ?");
            $stmt->execute([$typeNom, $typeDesc, $typeMontant, $typeDuree, $typeEditId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cotisation_types (nom, description, montant, duree_mois, actif) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$typeNom, $typeDesc, $typeMontant, $typeDuree]);
        }
        setFlashMessage('success', 'Type de cotisation enregistré.');
        header('Location: cotisations.php?tab=types');
        exit;
    }
}

// Récupérer les types de cotisation
$cotisationTypes = $pdo->query("SELECT * FROM cotisation_types WHERE actif = 1 ORDER BY montant")->fetchAll();

// Récupérer les membres
$membres = $pdo->query("SELECT id, nom, prenom, email FROM users WHERE role = 'membre' AND actif = 1 ORDER BY nom, prenom")->fetchAll();

// Récupérer cotisation à éditer
$editCotisation = null;
if ($action === 'edit' && $cotisationId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM cotisations WHERE id = ?");
    $stmt->execute([$cotisationId]);
    $editCotisation = $stmt->fetch();
}

// Pré-remplir user_id si passé en paramètre
$prefilledUserId = (int)($_GET['user_id'] ?? 0);

// Onglet actif
$tab = $_GET['tab'] ?? 'liste';

// Filtres
$filterStatut = $_GET['statut'] ?? '';
$filterExpire = $_GET['expire'] ?? '';

// Liste des cotisations
$sql = "SELECT c.*, ct.nom as type_nom, u.nom, u.prenom, u.email
        FROM cotisations c
        JOIN cotisation_types ct ON c.cotisation_type_id = ct.id
        JOIN users u ON c.user_id = u.id
        WHERE 1=1";
$params = [];

if ($filterStatut) {
    $sql .= " AND c.statut = ?";
    $params[] = $filterStatut;
}
if ($filterExpire === 'expire') {
    $sql .= " AND c.date_fin < CURDATE() AND c.statut = 'paye'";
} elseif ($filterExpire === 'bientot') {
    $sql .= " AND c.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND c.statut = 'paye'";
} elseif ($filterExpire === 'valide') {
    $sql .= " AND c.date_fin >= CURDATE() AND c.statut = 'paye'";
}

$sql .= " ORDER BY c.date_fin DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cotisations = $stmt->fetchAll();

// Stats
$stats = [
    'total' => 0,
    'en_attente' => 0,
    'a_jour' => 0,
    'expire_bientot' => 0,
    'expirees' => 0,
    'revenus_annee' => 0
];

$stmt = $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'en_attente'");
$stats['en_attente'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'paye' AND date_fin >= CURDATE()");
$stats['a_jour'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'paye' AND date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$stats['expire_bientot'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'paye' AND date_fin < CURDATE()");
$stats['expirees'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM cotisations WHERE statut = 'paye' AND YEAR(date_paiement) = YEAR(CURDATE())");
$stats['revenus_annee'] = $stmt->fetchColumn();

// Rafraîchir les types
$cotisationTypes = $pdo->query("SELECT * FROM cotisation_types WHERE actif = 1 ORDER BY montant")->fetchAll();

// Maintenant inclure le header (après tout le traitement POST)
$pageTitle = 'Cotisations';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon success">&#8364;</div>
        <div class="stat-content">
            <h4><?php echo number_format($stats['revenus_annee'], 2); ?> €</h4>
            <p>Revenus <?php echo date('Y'); ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary">&#10004;</div>
        <div class="stat-content">
            <h4><?php echo $stats['a_jour']; ?></h4>
            <p>Cotisations à jour</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning">&#9888;</div>
        <div class="stat-content">
            <h4><?php echo $stats['expire_bientot']; ?></h4>
            <p>Expirent bientôt</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(255,152,0,0.15);color:#e65100;">&#8987;</div>
        <div class="stat-content">
            <h4><?php echo $stats['en_attente']; ?></h4>
            <p>En attente de paiement</p>
        </div>
    </div>
</div>

<!-- Onglets -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <a href="?tab=liste" class="btn <?php echo $tab === 'liste' ? 'btn-primary' : 'btn-secondary'; ?>">Liste des cotisations</a>
    <a href="?tab=types" class="btn <?php echo $tab === 'types' ? 'btn-primary' : 'btn-secondary'; ?>">Types de cotisation</a>
</div>

<?php if ($tab === 'types'): ?>
<!-- Gestion des types de cotisation -->
<div class="card">
    <div class="card-header">
        <h3>Types de cotisation</h3>
    </div>
    <div class="card-body">
        <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:30px;padding:20px;background:#f9f9f9;border-radius:8px;">
            <input type="hidden" name="save_type" value="1">
            <input type="hidden" name="type_edit_id" value="0">
            <div class="form-group" style="margin:0;">
                <label>Nom *</label>
                <input type="text" name="type_nom" required placeholder="Ex: Annuelle">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Montant (€) *</label>
                <input type="number" name="type_montant" step="0.01" min="0" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Durée (mois)</label>
                <input type="number" name="type_duree" value="12" min="1">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Description</label>
                <input type="text" name="type_description" placeholder="Optionnel">
            </div>
            <div style="display:flex;align-items:flex-end;">
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>

        <?php if (empty($cotisationTypes)): ?>
            <p style="color:#666;">Aucun type de cotisation défini.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Montant</th>
                        <th>Durée</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cotisationTypes as $type): ?>
                        <tr>
                            <td><strong><?php echo e($type['nom']); ?></strong></td>
                            <td><?php echo number_format($type['montant'], 2); ?> €</td>
                            <td><?php echo $type['duree_mois']; ?> mois</td>
                            <td style="color:#666;"><?php echo e($type['description'] ?: '-'); ?></td>
                            <td>
                                <a href="?action=delete_type&type_id=<?php echo $type['id']; ?>&tab=types"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Supprimer ce type ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- Liste des cotisations -->

<!-- Modal Ajouter/Modifier -->
<div class="modal-overlay <?php echo ($action === 'add' || $editCotisation) ? 'active' : ''; ?>" id="cotisationModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo $editCotisation ? 'Modifier la cotisation' : 'Nouvelle cotisation'; ?></h3>
            <button class="modal-close" onclick="window.location.href='cotisations.php'">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <input type="hidden" name="save_cotisation" value="1">
                <input type="hidden" name="edit_id" value="<?php echo $editCotisation ? $editCotisation['id'] : 0; ?>">

                <div class="form-group">
                    <label>Membre *</label>
                    <select name="user_id" required id="cotis_user_select">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($membres as $m): ?>
                            <option value="<?php echo $m['id']; ?>"
                                <?php echo (($editCotisation['user_id'] ?? $prefilledUserId) == $m['id']) ? 'selected' : ''; ?>>
                                <?php echo e($m['prenom'] . ' ' . $m['nom'] . ' (' . $m['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Type de cotisation *</label>
                        <select name="cotisation_type_id" required id="cotis_type_select" onchange="updateCotisationFields()">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($cotisationTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>"
                                    data-montant="<?php echo $type['montant']; ?>"
                                    data-duree="<?php echo $type['duree_mois']; ?>"
                                    <?php echo ($editCotisation['cotisation_type_id'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($type['nom'] . ' - ' . number_format($type['montant'], 2) . ' €'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Montant (€)</label>
                        <input type="number" name="montant" id="cotis_montant" step="0.01" min="0"
                               value="<?php echo $editCotisation['montant'] ?? ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date de début *</label>
                        <input type="date" name="date_debut" id="cotis_date_debut" required
                               value="<?php echo $editCotisation['date_debut'] ?? date('Y-m-d'); ?>"
                               onchange="updateDateFin()">
                    </div>
                    <div class="form-group">
                        <label>Date de fin *</label>
                        <input type="date" name="date_fin" id="cotis_date_fin" required
                               value="<?php echo $editCotisation['date_fin'] ?? ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Statut</label>
                        <select name="statut">
                            <option value="en_attente" <?php echo ($editCotisation['statut'] ?? '') === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="paye" <?php echo ($editCotisation['statut'] ?? '') === 'paye' ? 'selected' : ''; ?>>Payé</option>
                            <option value="annule" <?php echo ($editCotisation['statut'] ?? '') === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mode de paiement</label>
                        <select name="mode_paiement">
                            <option value="especes" <?php echo ($editCotisation['mode_paiement'] ?? '') === 'especes' ? 'selected' : ''; ?>>Espèces</option>
                            <option value="cheque" <?php echo ($editCotisation['mode_paiement'] ?? '') === 'cheque' ? 'selected' : ''; ?>>Chèque</option>
                            <option value="carte" <?php echo ($editCotisation['mode_paiement'] ?? '') === 'carte' ? 'selected' : ''; ?>>Carte</option>
                            <option value="virement" <?php echo ($editCotisation['mode_paiement'] ?? '') === 'virement' ? 'selected' : ''; ?>>Virement</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Date de paiement</label>
                    <input type="date" name="date_paiement" value="<?php echo $editCotisation['date_paiement'] ?? ''; ?>">
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2"><?php echo e($editCotisation['notes'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <a href="cotisations.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary"><?php echo $editCotisation ? 'Modifier' : 'Créer'; ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function updateCotisationFields() {
    var select = document.getElementById('cotis_type_select');
    var option = select.options[select.selectedIndex];
    if (option.value) {
        document.getElementById('cotis_montant').value = option.dataset.montant;
        updateDateFin();
    }
}

function updateDateFin() {
    var select = document.getElementById('cotis_type_select');
    var option = select.options[select.selectedIndex];
    var dateDebut = document.getElementById('cotis_date_debut').value;

    if (option.value && dateDebut) {
        var duree = parseInt(option.dataset.duree) || 12;
        var date = new Date(dateDebut);
        date.setMonth(date.getMonth() + duree);
        date.setDate(date.getDate() - 1);
        document.getElementById('cotis_date_fin').value = date.toISOString().split('T')[0];
    }
}
</script>

<div class="card">
    <div class="card-header">
        <h3>Cotisations (<?php echo count($cotisations); ?>)</h3>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Nouvelle cotisation</a>
    </div>
    <div class="card-body">
        <!-- Filtres -->
        <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
            <select name="statut" style="width:auto;">
                <option value="">Tous statuts</option>
                <option value="en_attente" <?php echo $filterStatut === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                <option value="paye" <?php echo $filterStatut === 'paye' ? 'selected' : ''; ?>>Payé</option>
                <option value="annule" <?php echo $filterStatut === 'annule' ? 'selected' : ''; ?>>Annulé</option>
            </select>
            <select name="expire" style="width:auto;">
                <option value="">Toutes périodes</option>
                <option value="valide" <?php echo $filterExpire === 'valide' ? 'selected' : ''; ?>>Valides</option>
                <option value="bientot" <?php echo $filterExpire === 'bientot' ? 'selected' : ''; ?>>Expirent dans 30j</option>
                <option value="expire" <?php echo $filterExpire === 'expire' ? 'selected' : ''; ?>>Expirées</option>
            </select>
            <button type="submit" class="btn btn-sm btn-secondary">Filtrer</button>
            <?php if ($filterStatut || $filterExpire): ?>
                <a href="cotisations.php" class="btn btn-sm" style="background:#eee;">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <?php if (empty($cotisations)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#8364;</div>
                <h3>Aucune cotisation</h3>
                <p>Ajoutez votre première cotisation.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Type</th>
                            <th>Période</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cotisations as $cot): ?>
                            <?php
                            $isExpired = strtotime($cot['date_fin']) < time();
                            $expireSoon = !$isExpired && strtotime($cot['date_fin']) < strtotime('+30 days');
                            ?>
                            <tr>
                                <td>
                                    <a href="membres.php?action=view&id=<?php echo $cot['user_id']; ?>">
                                        <strong><?php echo e($cot['prenom'] . ' ' . $cot['nom']); ?></strong>
                                    </a>
                                    <div style="font-size:12px;color:#666;"><?php echo e($cot['email']); ?></div>
                                </td>
                                <td><?php echo e($cot['type_nom']); ?></td>
                                <td>
                                    <?php echo formatDate($cot['date_debut']); ?> - <?php echo formatDate($cot['date_fin']); ?>
                                    <?php if ($cot['statut'] === 'paye'): ?>
                                        <?php if ($isExpired): ?>
                                            <br><span class="badge badge-danger">Expirée</span>
                                        <?php elseif ($expireSoon): ?>
                                            <br><span class="badge" style="background:rgba(255,152,0,0.15);color:#e65100;">Expire bientôt</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($cot['montant'], 2); ?> €</td>
                                <td>
                                    <?php if ($cot['statut'] === 'paye'): ?>
                                        <span class="badge badge-success">Payé</span>
                                    <?php elseif ($cot['statut'] === 'en_attente'): ?>
                                        <span class="badge" style="background:rgba(255,152,0,0.15);color:#e65100;">En attente</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Annulé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($cot['statut'] === 'en_attente'): ?>
                                            <a href="?action=payer&id=<?php echo $cot['id']; ?>" class="btn btn-sm btn-success">Marquer payé</a>
                                        <?php endif; ?>
                                        <?php if ($cot['statut'] === 'paye'): ?>
                                            <a href="recu-pdf.php?type=cotisation&id=<?php echo $cot['id']; ?>" class="btn btn-sm btn-primary" target="_blank" title="Imprimer le reçu">Reçu PDF</a>
                                        <?php endif; ?>
                                        <a href="?action=edit&id=<?php echo $cot['id']; ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                        <a href="?action=delete&id=<?php echo $cot['id']; ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('Supprimer cette cotisation ?');">Supprimer</a>
                                    </div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Mini CRM - Gestion des sondages et votes
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('gestionnaire');

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? '';
$sondageId = (int)($_GET['id'] ?? 0);

// Supprimer un sondage
if ($action === 'delete' && $sondageId > 0) {
    $stmt = $pdo->prepare("DELETE FROM sondages WHERE id = ?");
    $stmt->execute([$sondageId]);
    setFlashMessage('success', 'Sondage supprimé.');
    header('Location: sondages.php');
    exit;
}

// Clôturer un sondage
if ($action === 'close' && $sondageId > 0) {
    $stmt = $pdo->prepare("UPDATE sondages SET statut = 'cloture' WHERE id = ?");
    $stmt->execute([$sondageId]);
    setFlashMessage('success', 'Sondage clôturé.');
    header('Location: sondages.php?action=results&id=' . $sondageId);
    exit;
}

// Activer un sondage
if ($action === 'activate' && $sondageId > 0) {
    $stmt = $pdo->prepare("UPDATE sondages SET statut = 'actif' WHERE id = ?");
    $stmt->execute([$sondageId]);
    setFlashMessage('success', 'Sondage activé.');
    header('Location: sondages.php');
    exit;
}

// Enregistrer un sondage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sondage'])) {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type_sondage'] ?? 'sondage';
    $dateDebut = $_POST['date_debut'] . ' ' . ($_POST['heure_debut'] ?: '00:00');
    $dateFin = $_POST['date_fin'] . ' ' . ($_POST['heure_fin'] ?: '23:59');
    $anonyme = isset($_POST['anonyme']) ? 1 : 0;
    $choixMultiple = isset($_POST['choix_multiple']) ? 1 : 0;
    $nbChoixMax = (int)($_POST['nb_choix_max'] ?? 1);
    $visibleResultat = $_POST['visible_resultat'] ?? 'apres_cloture';
    $electeurs = $_POST['electeurs'] ?? 'membres';
    $options = array_filter(array_map('trim', $_POST['options'] ?? []));

    if (empty($titre) || empty($options)) {
        setFlashMessage('error', 'Le titre et au moins une option sont obligatoires.');
    } else {
        if ($editId > 0) {
            $stmt = $pdo->prepare("UPDATE sondages SET titre = ?, description = ?, type_sondage = ?, date_debut = ?, date_fin = ?, anonyme = ?, choix_multiple = ?, nb_choix_max = ?, visible_resultat = ?, electeurs = ? WHERE id = ?");
            $stmt->execute([$titre, $description, $type, $dateDebut, $dateFin, $anonyme, $choixMultiple, $nbChoixMax, $visibleResultat, $electeurs, $editId]);

            // Mettre à jour les options
            $pdo->prepare("DELETE FROM sondage_options WHERE sondage_id = ?")->execute([$editId]);
            $ordre = 0;
            foreach ($options as $opt) {
                $stmt = $pdo->prepare("INSERT INTO sondage_options (sondage_id, texte, ordre) VALUES (?, ?, ?)");
                $stmt->execute([$editId, $opt, $ordre++]);
            }
            setFlashMessage('success', 'Sondage modifié.');
            $sondageId = $editId;
        } else {
            $stmt = $pdo->prepare("INSERT INTO sondages (titre, description, type_sondage, date_debut, date_fin, anonyme, choix_multiple, nb_choix_max, visible_resultat, electeurs, statut, cree_par, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon', ?, NOW())");
            $stmt->execute([$titre, $description, $type, $dateDebut, $dateFin, $anonyme, $choixMultiple, $nbChoixMax, $visibleResultat, $electeurs, $currentUser['id']]);
            $sondageId = $pdo->lastInsertId();

            // Ajouter les options
            $ordre = 0;
            foreach ($options as $opt) {
                $stmt = $pdo->prepare("INSERT INTO sondage_options (sondage_id, texte, ordre) VALUES (?, ?, ?)");
                $stmt->execute([$sondageId, $opt, $ordre++]);
            }
            setFlashMessage('success', 'Sondage créé.');
        }
        header('Location: sondages.php?action=results&id=' . $sondageId);
        exit;
    }
}

$pageTitle = 'Sondages & Votes';
require_once __DIR__ . '/../includes/header.php';

// Liste des sondages
$sondages = $pdo->query("
    SELECT s.*,
           (SELECT COUNT(DISTINCT user_id) FROM sondage_votes WHERE sondage_id = s.id) as nb_votants,
           (SELECT COUNT(*) FROM sondage_options WHERE sondage_id = s.id) as nb_options
    FROM sondages s
    ORDER BY s.created_at DESC
")->fetchAll();

// Détails/résultats d'un sondage
$viewSondage = null;
$optionsResults = [];
if (($action === 'results' || $action === 'edit') && $sondageId > 0) {
    $stmt = $pdo->prepare("SELECT s.*, u.nom as createur_nom, u.prenom as createur_prenom
                           FROM sondages s
                           LEFT JOIN users u ON s.cree_par = u.id
                           WHERE s.id = ?");
    $stmt->execute([$sondageId]);
    $viewSondage = $stmt->fetch();

    if ($viewSondage) {
        $stmt = $pdo->prepare("SELECT o.*,
                               (SELECT COUNT(*) FROM sondage_votes v WHERE v.option_id = o.id) as nb_votes
                               FROM sondage_options o
                               WHERE o.sondage_id = ?
                               ORDER BY o.ordre");
        $stmt->execute([$sondageId]);
        $optionsResults = $stmt->fetchAll();
    }
}

$typeLabels = [
    'sondage' => ['label' => 'Sondage', 'color' => '#3b82f6', 'icon' => '&#128202;'],
    'vote' => ['label' => 'Vote', 'color' => '#10b981', 'icon' => '&#9989;'],
    'election' => ['label' => 'Élection', 'color' => '#8b5cf6', 'icon' => '&#127895;']
];

$statutLabels = [
    'brouillon' => ['label' => 'Brouillon', 'class' => ''],
    'actif' => ['label' => 'Actif', 'class' => 'badge-success'],
    'cloture' => ['label' => 'Clôturé', 'class' => 'badge-danger']
];
?>

<style>
.poll-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.2s;
}
.poll-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.poll-type {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
}
.result-bar {
    background: #f3f4f6;
    border-radius: 8px;
    overflow: hidden;
    margin: 10px 0;
}
.result-bar .fill {
    height: 40px;
    display: flex;
    align-items: center;
    padding: 0 15px;
    color: #fff;
    font-weight: 500;
    transition: width 0.5s ease;
}
.option-input {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}
.option-input input {
    flex: 1;
}
</style>

<?php if ($action === 'results' && $viewSondage): ?>
<!-- Résultats du sondage -->
<div class="card">
    <div class="card-header">
        <div>
            <span class="poll-type" style="background: <?php echo $typeLabels[$viewSondage['type_sondage']]['color']; ?>15; color: <?php echo $typeLabels[$viewSondage['type_sondage']]['color']; ?>;">
                <?php echo $typeLabels[$viewSondage['type_sondage']]['icon']; ?>
                <?php echo $typeLabels[$viewSondage['type_sondage']]['label']; ?>
            </span>
            <h3 style="margin: 10px 0 0;"><?php echo e($viewSondage['titre']); ?></h3>
        </div>
        <div class="btn-group">
            <?php if ($viewSondage['statut'] === 'brouillon'): ?>
                <a href="?action=activate&id=<?php echo $viewSondage['id']; ?>" class="btn btn-sm btn-success">Activer</a>
                <a href="?action=edit&id=<?php echo $viewSondage['id']; ?>" class="btn btn-sm btn-secondary">Modifier</a>
            <?php elseif ($viewSondage['statut'] === 'actif'): ?>
                <a href="?action=close&id=<?php echo $viewSondage['id']; ?>" onclick="return confirm('Clôturer ce sondage ?')" class="btn btn-sm btn-warning">Clôturer</a>
            <?php endif; ?>
            <a href="sondages.php" class="btn btn-sm btn-secondary">Retour</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($viewSondage['description']): ?>
            <p style="color: #666; margin-bottom: 20px;"><?php echo nl2br(e($viewSondage['description'])); ?></p>
        <?php endif; ?>

        <div style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">
            <div>
                <span class="badge <?php echo $statutLabels[$viewSondage['statut']]['class']; ?>">
                    <?php echo $statutLabels[$viewSondage['statut']]['label']; ?>
                </span>
            </div>
            <div style="color: #666; font-size: 14px;">
                Du <?php echo date('d/m/Y H:i', strtotime($viewSondage['date_debut'])); ?>
                au <?php echo date('d/m/Y H:i', strtotime($viewSondage['date_fin'])); ?>
            </div>
            <div style="color: #666; font-size: 14px;">
                <?php echo $viewSondage['anonyme'] ? '&#128274; Anonyme' : '&#128275; Non anonyme'; ?>
            </div>
        </div>

        <?php
        $totalVotes = array_sum(array_column($optionsResults, 'nb_votes'));
        $nbVotants = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM sondage_votes WHERE sondage_id = " . $viewSondage['id'])->fetchColumn();
        ?>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
            <div style="background: #f0f7ff; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: var(--primary);"><?php echo $nbVotants; ?></div>
                <div style="color: #666;">Participant(s)</div>
            </div>
            <div style="background: #f0fdf4; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #16a34a;"><?php echo $totalVotes; ?></div>
                <div style="color: #666;">Vote(s) total</div>
            </div>
        </div>

        <h4 style="margin-bottom: 20px;">Résultats</h4>
        <?php
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
        $i = 0;
        $maxVotes = max(array_column($optionsResults, 'nb_votes'));

        foreach ($optionsResults as $opt):
            $pct = $totalVotes > 0 ? round($opt['nb_votes'] / $totalVotes * 100) : 0;
            $width = $maxVotes > 0 ? round($opt['nb_votes'] / $maxVotes * 100) : 0;
            $color = $colors[$i % count($colors)];
        ?>
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <strong><?php echo e($opt['texte']); ?></strong>
                    <span style="color: #666;"><?php echo $opt['nb_votes']; ?> vote(s) (<?php echo $pct; ?>%)</span>
                </div>
                <div class="result-bar">
                    <div class="fill" style="width: <?php echo max($width, 5); ?>%; background: <?php echo $color; ?>;">
                        <?php if ($width > 10): ?><?php echo $pct; ?>%<?php endif; ?>
                    </div>
                </div>
            </div>
        <?php $i++; endforeach; ?>

        <!-- Lien de partage -->
        <div style="margin-top: 30px; padding: 20px; background: #f9fafb; border-radius: 10px;">
            <h5 style="margin: 0 0 10px;">Lien de vote</h5>
            <div style="display: flex; gap: 10px;">
                <input type="text" id="voteLink" value="<?php echo getBaseUrl(); ?>/voter.php?id=<?php echo $viewSondage['id']; ?>" readonly style="flex: 1;">
                <button onclick="copyVoteLink()" class="btn btn-sm btn-secondary">Copier</button>
            </div>
        </div>
    </div>
</div>

<script>
function copyVoteLink() {
    var input = document.getElementById('voteLink');
    input.select();
    document.execCommand('copy');
    alert('Lien copié !');
}
</script>

<?php elseif ($action === 'add' || ($action === 'edit' && $viewSondage)): ?>
<!-- Formulaire -->
<div class="card">
    <div class="card-header">
        <h3><?php echo $viewSondage ? 'Modifier le sondage' : 'Nouveau sondage'; ?></h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="save_sondage" value="1">
            <input type="hidden" name="edit_id" value="<?php echo $viewSondage['id'] ?? 0; ?>">

            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label>Titre *</label>
                    <input type="text" name="titre" value="<?php echo e($viewSondage['titre'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type_sondage">
                        <?php foreach ($typeLabels as $k => $v): ?>
                            <option value="<?php echo $k; ?>" <?php echo ($viewSondage['type_sondage'] ?? '') === $k ? 'selected' : ''; ?>><?php echo $v['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?php echo e($viewSondage['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date de début</label>
                    <input type="date" name="date_debut" value="<?php echo $viewSondage ? date('Y-m-d', strtotime($viewSondage['date_debut'])) : date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Heure</label>
                    <input type="time" name="heure_debut" value="<?php echo $viewSondage ? date('H:i', strtotime($viewSondage['date_debut'])) : '00:00'; ?>">
                </div>
                <div class="form-group">
                    <label>Date de fin</label>
                    <input type="date" name="date_fin" value="<?php echo $viewSondage ? date('Y-m-d', strtotime($viewSondage['date_fin'])) : date('Y-m-d', strtotime('+7 days')); ?>" required>
                </div>
                <div class="form-group">
                    <label>Heure</label>
                    <input type="time" name="heure_fin" value="<?php echo $viewSondage ? date('H:i', strtotime($viewSondage['date_fin'])) : '23:59'; ?>">
                </div>
            </div>

            <div class="section-title">Options de réponse *</div>
            <div id="optionsContainer">
                <?php if ($viewSondage && !empty($optionsResults)): ?>
                    <?php foreach ($optionsResults as $opt): ?>
                        <div class="option-input">
                            <input type="text" name="options[]" value="<?php echo e($opt['texte']); ?>" placeholder="Option...">
                            <button type="button" onclick="removeOption(this)" class="btn btn-sm" style="color: #dc2626;">×</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="option-input">
                        <input type="text" name="options[]" placeholder="Option 1">
                        <button type="button" onclick="removeOption(this)" class="btn btn-sm" style="color: #dc2626;">×</button>
                    </div>
                    <div class="option-input">
                        <input type="text" name="options[]" placeholder="Option 2">
                        <button type="button" onclick="removeOption(this)" class="btn btn-sm" style="color: #dc2626;">×</button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" onclick="addOption()" class="btn btn-sm btn-secondary" style="margin-bottom: 20px;">+ Ajouter une option</button>

            <div class="section-title">Paramètres</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Qui peut voter ?</label>
                    <select name="electeurs">
                        <option value="tous" <?php echo ($viewSondage['electeurs'] ?? '') === 'tous' ? 'selected' : ''; ?>>Tous les utilisateurs</option>
                        <option value="membres" <?php echo ($viewSondage['electeurs'] ?? 'membres') === 'membres' ? 'selected' : ''; ?>>Membres uniquement</option>
                        <option value="admin" <?php echo ($viewSondage['electeurs'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admins/Gestionnaires</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Visibilité des résultats</label>
                    <select name="visible_resultat">
                        <option value="toujours" <?php echo ($viewSondage['visible_resultat'] ?? '') === 'toujours' ? 'selected' : ''; ?>>Toujours visibles</option>
                        <option value="apres_vote" <?php echo ($viewSondage['visible_resultat'] ?? '') === 'apres_vote' ? 'selected' : ''; ?>>Après avoir voté</option>
                        <option value="apres_cloture" <?php echo ($viewSondage['visible_resultat'] ?? 'apres_cloture') === 'apres_cloture' ? 'selected' : ''; ?>>Après clôture</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 30px; margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="anonyme" <?php echo ($viewSondage['anonyme'] ?? 0) ? 'checked' : ''; ?>>
                    Vote anonyme
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="choix_multiple" <?php echo ($viewSondage['choix_multiple'] ?? 0) ? 'checked' : ''; ?>>
                    Choix multiple
                </label>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="sondages.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
function addOption() {
    var container = document.getElementById('optionsContainer');
    var count = container.children.length + 1;
    var div = document.createElement('div');
    div.className = 'option-input';
    div.innerHTML = '<input type="text" name="options[]" placeholder="Option ' + count + '">' +
                    '<button type="button" onclick="removeOption(this)" class="btn btn-sm" style="color: #dc2626;">×</button>';
    container.appendChild(div);
}

function removeOption(btn) {
    var container = document.getElementById('optionsContainer');
    if (container.children.length > 2) {
        btn.parentElement.remove();
    }
}
</script>

<?php else: ?>
<!-- Liste des sondages -->
<div class="card">
    <div class="card-header">
        <h3>Sondages & Votes</h3>
        <a href="?action=add" class="btn btn-sm btn-primary">+ Nouveau sondage</a>
    </div>
    <div class="card-body">
        <?php if (empty($sondages)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128202;</div>
                <h3>Aucun sondage</h3>
                <p>Créez votre premier sondage ou vote.</p>
                <a href="?action=add" class="btn btn-primary">Créer un sondage</a>
            </div>
        <?php else: ?>
            <?php foreach ($sondages as $s): ?>
                <div class="poll-card">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <span class="poll-type" style="background: <?php echo $typeLabels[$s['type_sondage']]['color']; ?>15; color: <?php echo $typeLabels[$s['type_sondage']]['color']; ?>;">
                                <?php echo $typeLabels[$s['type_sondage']]['icon']; ?>
                                <?php echo $typeLabels[$s['type_sondage']]['label']; ?>
                            </span>
                            <span class="badge <?php echo $statutLabels[$s['statut']]['class']; ?>" style="margin-left: 10px;">
                                <?php echo $statutLabels[$s['statut']]['label']; ?>
                            </span>
                            <h4 style="margin: 10px 0 5px;"><?php echo e($s['titre']); ?></h4>
                            <div style="font-size: 13px; color: #666;">
                                <?php echo date('d/m/Y', strtotime($s['date_debut'])); ?> - <?php echo date('d/m/Y', strtotime($s['date_fin'])); ?>
                                | <?php echo $s['nb_options']; ?> options
                                | <?php echo $s['nb_votants']; ?> participant(s)
                            </div>
                        </div>
                        <div class="btn-group">
                            <a href="?action=results&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary">Voir</a>
                            <a href="?action=delete&id=<?php echo $s['id']; ?>" onclick="return confirm('Supprimer ?')" class="btn btn-sm" style="color:#dc2626;">Supprimer</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Mini CRM - Page de vote publique
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$sondageId = (int)($_GET['id'] ?? 0);
$error = '';
$success = false;
$hasVoted = false;

// Récupérer le sondage
$sondage = null;
$options = [];
if ($sondageId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM sondages WHERE id = ? AND statut = 'actif'");
    $stmt->execute([$sondageId]);
    $sondage = $stmt->fetch();

    if ($sondage) {
        // Vérifier les dates
        $now = new DateTime();
        $debut = new DateTime($sondage['date_debut']);
        $fin = new DateTime($sondage['date_fin']);

        if ($now < $debut || $now > $fin) {
            $sondage = null;
            $error = 'Ce sondage n\'est pas actuellement ouvert au vote.';
        } else {
            // Vérifier si l'utilisateur peut voter
            if ($sondage['electeurs'] === 'membres' && $currentUser['role'] !== 'membre' && $currentUser['role'] !== 'gestionnaire' && $currentUser['role'] !== 'admin') {
                $error = 'Ce sondage est réservé aux membres.';
            } elseif ($sondage['electeurs'] === 'admin' && $currentUser['role'] !== 'gestionnaire' && $currentUser['role'] !== 'admin') {
                $error = 'Ce sondage est réservé aux administrateurs.';
            }

            // Vérifier si déjà voté
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sondage_votes WHERE sondage_id = ? AND user_id = ?");
            $stmt->execute([$sondageId, $currentUser['id']]);
            $hasVoted = $stmt->fetchColumn() > 0;

            // Récupérer les options
            $stmt = $pdo->prepare("SELECT * FROM sondage_options WHERE sondage_id = ? ORDER BY ordre");
            $stmt->execute([$sondageId]);
            $options = $stmt->fetchAll();
        }
    }
}

// Traiter le vote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sondage && !$hasVoted && !$error) {
    $selectedOptions = $_POST['options'] ?? [];

    if (empty($selectedOptions)) {
        $error = 'Veuillez sélectionner au moins une option.';
    } elseif (!$sondage['choix_multiple'] && count($selectedOptions) > 1) {
        $error = 'Vous ne pouvez sélectionner qu\'une seule option.';
    } elseif ($sondage['choix_multiple'] && count($selectedOptions) > $sondage['nb_choix_max']) {
        $error = 'Vous ne pouvez sélectionner que ' . $sondage['nb_choix_max'] . ' option(s) maximum.';
    } else {
        foreach ($selectedOptions as $optionId) {
            $stmt = $pdo->prepare("INSERT INTO sondage_votes (sondage_id, option_id, user_id, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$sondageId, (int)$optionId, $currentUser['id']]);
        }
        $success = true;
        $hasVoted = true;
    }
}

// Résultats
$results = [];
$totalVotes = 0;
if ($sondage && ($hasVoted || $sondage['visible_resultat'] === 'toujours')) {
    foreach ($options as &$opt) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sondage_votes WHERE option_id = ?");
        $stmt->execute([$opt['id']]);
        $opt['votes'] = $stmt->fetchColumn();
        $totalVotes += $opt['votes'];
    }
}

$pageTitle = $sondage ? $sondage['titre'] : 'Vote';
require_once __DIR__ . '/includes/header.php';

$typeLabels = [
    'sondage' => ['label' => 'Sondage', 'color' => '#3b82f6'],
    'vote' => ['label' => 'Vote', 'color' => '#10b981'],
    'election' => ['label' => 'Élection', 'color' => '#8b5cf6']
];
?>

<style>
.vote-container {
    max-width: 700px;
    margin: 0 auto;
}
.vote-option {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 12px;
    cursor: pointer;
    transition: all 0.2s;
}
.vote-option:hover {
    border-color: var(--primary);
    background: #f0f7ff;
}
.vote-option.selected {
    border-color: var(--primary);
    background: #dbeafe;
}
.vote-option input {
    width: 20px;
    height: 20px;
}
.vote-option .label {
    flex: 1;
    font-size: 16px;
}
.result-option {
    padding: 15px;
    background: #f9fafb;
    border-radius: 12px;
    margin-bottom: 12px;
}
.result-option .bar-container {
    background: #e5e7eb;
    border-radius: 8px;
    height: 30px;
    overflow: hidden;
    margin-top: 10px;
}
.result-option .bar {
    height: 100%;
    display: flex;
    align-items: center;
    padding: 0 10px;
    color: #fff;
    font-weight: 500;
    font-size: 14px;
}
.success-box {
    background: #f0fdf4;
    border: 2px solid #86efac;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
}
.success-box .icon { font-size: 64px; }
</style>

<div class="vote-container">
    <?php if (!$sondage && !$error): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 60px;">
                <div style="font-size: 64px;">&#128202;</div>
                <h3>Sondage non trouvé</h3>
                <p style="color: #666;">Ce sondage n'existe pas ou n'est plus disponible.</p>
            </div>
        </div>

    <?php elseif ($error && !$sondage): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 60px;">
                <div style="font-size: 64px;">&#128683;</div>
                <h3>Accès refusé</h3>
                <p style="color: #666;"><?php echo e($error); ?></p>
            </div>
        </div>

    <?php elseif ($success): ?>
        <div class="card">
            <div class="card-body">
                <div class="success-box">
                    <div class="icon">&#9989;</div>
                    <h2>Merci pour votre vote !</h2>
                    <p style="color: #666;">Votre participation a bien été enregistrée.</p>
                </div>

                <?php if ($sondage['visible_resultat'] !== 'apres_cloture'): ?>
                    <h4 style="margin: 30px 0 20px;">Résultats actuels</h4>
                    <?php
                    $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
                    $i = 0;
                    foreach ($options as $opt):
                        $pct = $totalVotes > 0 ? round($opt['votes'] / $totalVotes * 100) : 0;
                    ?>
                        <div class="result-option">
                            <div style="display: flex; justify-content: space-between;">
                                <strong><?php echo e($opt['texte']); ?></strong>
                                <span><?php echo $opt['votes']; ?> vote(s) (<?php echo $pct; ?>%)</span>
                            </div>
                            <div class="bar-container">
                                <div class="bar" style="width: <?php echo max($pct, 3); ?>%; background: <?php echo $colors[$i % 5]; ?>;">
                                    <?php if ($pct > 10) echo $pct . '%'; ?>
                                </div>
                            </div>
                        </div>
                    <?php $i++; endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; margin-top: 30px;">
                        Les résultats seront visibles après la clôture du sondage.
                    </p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($hasVoted): ?>
        <div class="card">
            <div class="card-header">
                <span style="padding: 4px 12px; border-radius: 20px; background: <?php echo $typeLabels[$sondage['type_sondage']]['color']; ?>15; color: <?php echo $typeLabels[$sondage['type_sondage']]['color']; ?>; font-size: 13px;">
                    <?php echo $typeLabels[$sondage['type_sondage']]['label']; ?>
                </span>
                <h3 style="margin: 10px 0 0;"><?php echo e($sondage['titre']); ?></h3>
            </div>
            <div class="card-body">
                <div style="background: #fef3c7; border: 1px solid #fcd34d; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                    <strong>Vous avez déjà participé à ce sondage.</strong>
                </div>

                <?php if ($sondage['visible_resultat'] !== 'apres_cloture'): ?>
                    <h4 style="margin-bottom: 20px;">Résultats actuels</h4>
                    <?php
                    $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
                    $i = 0;
                    foreach ($options as $opt):
                        $pct = $totalVotes > 0 ? round($opt['votes'] / $totalVotes * 100) : 0;
                    ?>
                        <div class="result-option">
                            <div style="display: flex; justify-content: space-between;">
                                <strong><?php echo e($opt['texte']); ?></strong>
                                <span><?php echo $opt['votes']; ?> vote(s) (<?php echo $pct; ?>%)</span>
                            </div>
                            <div class="bar-container">
                                <div class="bar" style="width: <?php echo max($pct, 3); ?>%; background: <?php echo $colors[$i % 5]; ?>;">
                                    <?php if ($pct > 10) echo $pct . '%'; ?>
                                </div>
                            </div>
                        </div>
                    <?php $i++; endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <span style="padding: 4px 12px; border-radius: 20px; background: <?php echo $typeLabels[$sondage['type_sondage']]['color']; ?>15; color: <?php echo $typeLabels[$sondage['type_sondage']]['color']; ?>; font-size: 13px;">
                    <?php echo $typeLabels[$sondage['type_sondage']]['label']; ?>
                </span>
                <h3 style="margin: 10px 0 0;"><?php echo e($sondage['titre']); ?></h3>
            </div>
            <div class="card-body">
                <?php if ($sondage['description']): ?>
                    <p style="color: #666; margin-bottom: 20px;"><?php echo nl2br(e($sondage['description'])); ?></p>
                <?php endif; ?>

                <div style="display: flex; gap: 20px; margin-bottom: 25px; font-size: 14px; color: #666;">
                    <span>&#128197; Jusqu'au <?php echo date('d/m/Y à H:i', strtotime($sondage['date_fin'])); ?></span>
                    <?php if ($sondage['choix_multiple']): ?>
                        <span>&#9745; Choix multiple (max <?php echo $sondage['nb_choix_max']; ?>)</span>
                    <?php else: ?>
                        <span>&#9745; Choix unique</span>
                    <?php endif; ?>
                    <?php if ($sondage['anonyme']): ?>
                        <span>&#128274; Vote anonyme</span>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php foreach ($options as $opt): ?>
                        <label class="vote-option" onclick="<?php echo $sondage['choix_multiple'] ? '' : 'selectSingle(this)'; ?>">
                            <input type="<?php echo $sondage['choix_multiple'] ? 'checkbox' : 'radio'; ?>"
                                   name="options[]"
                                   value="<?php echo $opt['id']; ?>">
                            <span class="label"><?php echo e($opt['texte']); ?></span>
                        </label>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px; padding: 15px;">
                        Voter
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function selectSingle(el) {
    document.querySelectorAll('.vote-option').forEach(function(opt) {
        opt.classList.remove('selected');
    });
    el.classList.add('selected');
}

document.querySelectorAll('.vote-option input').forEach(function(input) {
    input.addEventListener('change', function() {
        var option = this.closest('.vote-option');
        if (this.checked) {
            option.classList.add('selected');
        } else {
            option.classList.remove('selected');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Mini CRM - Programme de fidélité
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('gestionnaire');

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? '';
$tab = $_GET['tab'] ?? 'membres';

// Ajouter/retirer des points
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_points'])) {
    $userId = (int)$_POST['user_id'];
    $points = (int)$_POST['points'];
    $type = $_POST['type_transaction'];
    $description = trim($_POST['description'] ?? '');

    if ($userId > 0 && $points != 0) {
        // Insérer la transaction
        $stmt = $pdo->prepare("INSERT INTO fidelite_transactions (user_id, points, type_transaction, description, reference_type, created_by, created_at) VALUES (?, ?, ?, ?, 'manuel', ?, NOW())");
        $stmt->execute([$userId, $points, $type, $description, $currentUser['id']]);

        // Mettre à jour le solde
        $stmt = $pdo->prepare("UPDATE users SET points_fidelite = points_fidelite + ? WHERE id = ?");
        $stmt->execute([$points, $userId]);

        setFlashMessage('success', 'Points mis à jour.');
    }
    header('Location: fidelite.php?tab=membres');
    exit;
}

// Créer/modifier une récompense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recompense'])) {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $pointsRequis = (int)($_POST['points_requis'] ?? 0);
    $typeRecompense = $_POST['type_recompense'] ?? 'autre';
    $valeurReduction = (float)($_POST['valeur_reduction'] ?? 0);
    $stock = (int)($_POST['stock'] ?? -1);
    $actif = isset($_POST['actif']) ? 1 : 0;

    if (empty($nom) || $pointsRequis <= 0) {
        setFlashMessage('error', 'Nom et points requis sont obligatoires.');
    } else {
        if ($editId > 0) {
            $stmt = $pdo->prepare("UPDATE fidelite_recompenses SET nom = ?, description = ?, points_requis = ?, type_recompense = ?, valeur_reduction = ?, stock = ?, actif = ? WHERE id = ?");
            $stmt->execute([$nom, $description, $pointsRequis, $typeRecompense, $valeurReduction, $stock, $actif, $editId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO fidelite_recompenses (nom, description, points_requis, type_recompense, valeur_reduction, stock, actif, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nom, $description, $pointsRequis, $typeRecompense, $valeurReduction, $stock, $actif]);
        }
        setFlashMessage('success', 'Récompense enregistrée.');
    }
    header('Location: fidelite.php?tab=recompenses');
    exit;
}

// Supprimer une récompense
if ($action === 'delete_recompense' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM fidelite_recompenses WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    setFlashMessage('success', 'Récompense supprimée.');
    header('Location: fidelite.php?tab=recompenses');
    exit;
}

// Utiliser une récompense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_reward'])) {
    $userId = (int)$_POST['user_id'];
    $recompenseId = (int)$_POST['recompense_id'];

    $stmt = $pdo->prepare("SELECT * FROM fidelite_recompenses WHERE id = ? AND actif = 1");
    $stmt->execute([$recompenseId]);
    $recompense = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT points_fidelite FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($recompense && $user && $user['points_fidelite'] >= $recompense['points_requis']) {
        // Créer l'utilisation
        $stmt = $pdo->prepare("INSERT INTO fidelite_utilisations (user_id, recompense_id, points_utilises, statut, valide_par, created_at) VALUES (?, ?, ?, 'valide', ?, NOW())");
        $stmt->execute([$userId, $recompenseId, $recompense['points_requis'], $currentUser['id']]);

        // Déduire les points
        $stmt = $pdo->prepare("UPDATE users SET points_fidelite = points_fidelite - ? WHERE id = ?");
        $stmt->execute([$recompense['points_requis'], $userId]);

        // Transaction
        $stmt = $pdo->prepare("INSERT INTO fidelite_transactions (user_id, points, type_transaction, description, reference_type, reference_id, created_by, created_at) VALUES (?, ?, 'utilisation', ?, 'manuel', ?, ?, NOW())");
        $stmt->execute([$userId, -$recompense['points_requis'], 'Récompense: ' . $recompense['nom'], $recompenseId, $currentUser['id']]);

        // Décrémenter le stock si limité
        if ($recompense['stock'] > 0) {
            $stmt = $pdo->prepare("UPDATE fidelite_recompenses SET stock = stock - 1 WHERE id = ?");
            $stmt->execute([$recompenseId]);
        }

        setFlashMessage('success', 'Récompense utilisée avec succès.');
    } else {
        setFlashMessage('error', 'Points insuffisants ou récompense indisponible.');
    }
    header('Location: fidelite.php?tab=membres');
    exit;
}

// Mettre à jour les paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    setSetting('fidelite_actif', isset($_POST['fidelite_actif']) ? '1' : '0');
    setSetting('fidelite_points_cotisation', (int)$_POST['points_cotisation']);
    setSetting('fidelite_points_evenement', (int)$_POST['points_evenement']);
    setSetting('fidelite_points_benevole', (int)$_POST['points_benevole']);
    setSetting('fidelite_points_parrainage', (int)$_POST['points_parrainage']);
    setSetting('fidelite_points_anniversaire', (int)$_POST['points_anniversaire']);

    setFlashMessage('success', 'Paramètres enregistrés.');
    header('Location: fidelite.php?tab=parametres');
    exit;
}

$pageTitle = 'Programme Fidélité';
require_once __DIR__ . '/../includes/header.php';

// Récupérer les statistiques
$statsPoints = $pdo->query("SELECT SUM(points_fidelite) as total, COUNT(CASE WHEN points_fidelite > 0 THEN 1 END) as avec_points FROM users WHERE actif = 1")->fetch();
$statsRecompenses = $pdo->query("SELECT COUNT(*) as total FROM fidelite_utilisations WHERE statut = 'valide'")->fetch();

// Récupérer les membres avec leurs points
$membres = $pdo->query("SELECT u.*, (SELECT SUM(points) FROM fidelite_transactions WHERE user_id = u.id AND type_transaction = 'gain') as total_gagnes, (SELECT SUM(ABS(points)) FROM fidelite_transactions WHERE user_id = u.id AND type_transaction = 'utilisation') as total_utilises FROM users u WHERE u.actif = 1 AND u.role = 'membre' ORDER BY u.points_fidelite DESC")->fetchAll();

// Récupérer les récompenses
$recompenses = $pdo->query("SELECT * FROM fidelite_recompenses ORDER BY points_requis")->fetchAll();

// Récupérer les paramètres
$settings = [
    'actif' => getSetting('fidelite_actif', '1') === '1',
    'points_cotisation' => (int)getSetting('fidelite_points_cotisation', '100'),
    'points_evenement' => (int)getSetting('fidelite_points_evenement', '50'),
    'points_benevole' => (int)getSetting('fidelite_points_benevole', '200'),
    'points_parrainage' => (int)getSetting('fidelite_points_parrainage', '150'),
    'points_anniversaire' => (int)getSetting('fidelite_points_anniversaire', '50')
];

// Historique des transactions
$transactions = $pdo->query("SELECT t.*, u.nom, u.prenom FROM fidelite_transactions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 50")->fetchAll();
?>

<style>
.tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0;
}
.tabs a {
    padding: 12px 20px;
    text-decoration: none;
    color: #6b7280;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}
.tabs a:hover { color: var(--primary); }
.tabs a.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    font-weight: 500;
}
.points-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: #fff;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 600;
}
.points-badge.large {
    font-size: 24px;
    padding: 10px 20px;
}
.reward-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.2s;
}
.reward-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.reward-card .icon {
    font-size: 48px;
    margin-bottom: 15px;
}
.reward-card h4 { margin: 0 0 10px; }
.reward-card .points {
    color: #f59e0b;
    font-weight: 600;
    font-size: 18px;
}
.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
}
.stat-box.gold { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
.stat-box.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-box .value { font-size: 36px; font-weight: bold; }
.stat-box .label { opacity: 0.9; margin-top: 5px; }
.transaction-row {
    display: flex;
    align-items: center;
    padding: 12px;
    border-bottom: 1px solid #f3f4f6;
}
.transaction-row:last-child { border-bottom: none; }
.transaction-row .points {
    font-weight: 600;
    min-width: 80px;
    text-align: right;
}
.transaction-row .points.positive { color: #10b981; }
.transaction-row .points.negative { color: #ef4444; }
</style>

<div class="card">
    <div class="card-header">
        <h3>Programme de Fidélité</h3>
    </div>
    <div class="card-body">
        <!-- Stats -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
            <div class="stat-box gold">
                <div class="value"><?php echo number_format($statsPoints['total'] ?? 0); ?></div>
                <div class="label">Points en circulation</div>
            </div>
            <div class="stat-box">
                <div class="value"><?php echo $statsPoints['avec_points'] ?? 0; ?></div>
                <div class="label">Membres avec points</div>
            </div>
            <div class="stat-box green">
                <div class="value"><?php echo $statsRecompenses['total'] ?? 0; ?></div>
                <div class="label">Récompenses utilisées</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=membres" class="<?php echo $tab === 'membres' ? 'active' : ''; ?>">Membres</a>
            <a href="?tab=recompenses" class="<?php echo $tab === 'recompenses' ? 'active' : ''; ?>">Récompenses</a>
            <a href="?tab=historique" class="<?php echo $tab === 'historique' ? 'active' : ''; ?>">Historique</a>
            <a href="?tab=parametres" class="<?php echo $tab === 'parametres' ? 'active' : ''; ?>">Paramètres</a>
        </div>

        <?php if ($tab === 'membres'): ?>
        <!-- Liste des membres -->
        <div style="margin-bottom: 15px;">
            <button onclick="openModal('addPointsModal')" class="btn btn-primary btn-sm">+ Ajouter des points</button>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Points actuels</th>
                        <th>Total gagné</th>
                        <th>Total utilisé</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($membres as $m): ?>
                        <tr>
                            <td>
                                <strong><?php echo e($m['prenom'] . ' ' . $m['nom']); ?></strong>
                                <br><span style="color: #666; font-size: 12px;"><?php echo e($m['email']); ?></span>
                            </td>
                            <td>
                                <span class="points-badge"><?php echo number_format($m['points_fidelite']); ?> pts</span>
                            </td>
                            <td style="color: #10b981;">+<?php echo number_format($m['total_gagnes'] ?? 0); ?></td>
                            <td style="color: #ef4444;">-<?php echo number_format($m['total_utilises'] ?? 0); ?></td>
                            <td>
                                <button onclick="showMemberActions(<?php echo $m['id']; ?>, '<?php echo e($m['prenom'] . ' ' . $m['nom']); ?>', <?php echo $m['points_fidelite']; ?>)"
                                        class="btn btn-sm btn-secondary">Actions</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($tab === 'recompenses'): ?>
        <!-- Récompenses -->
        <div style="margin-bottom: 20px;">
            <button onclick="openModal('recompenseModal')" class="btn btn-primary btn-sm">+ Nouvelle récompense</button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
            <?php foreach ($recompenses as $r): ?>
                <div class="reward-card" style="<?php echo !$r['actif'] ? 'opacity: 0.5;' : ''; ?>">
                    <div class="icon">
                        <?php
                        $icons = ['reduction' => '&#127873;', 'cadeau' => '&#127873;', 'acces_vip' => '&#11088;', 'autre' => '&#127942;'];
                        echo $icons[$r['type_recompense']] ?? '&#127942;';
                        ?>
                    </div>
                    <h4><?php echo e($r['nom']); ?></h4>
                    <p style="color: #666; font-size: 14px; min-height: 40px;"><?php echo e($r['description'] ?: '-'); ?></p>
                    <div class="points"><?php echo number_format($r['points_requis']); ?> points</div>
                    <?php if ($r['stock'] >= 0): ?>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">Stock: <?php echo $r['stock']; ?></div>
                    <?php endif; ?>
                    <div style="margin-top: 15px;">
                        <button onclick="editRecompense(<?php echo htmlspecialchars(json_encode($r)); ?>)" class="btn btn-sm btn-secondary">Modifier</button>
                        <a href="?action=delete_recompense&id=<?php echo $r['id']; ?>&tab=recompenses" onclick="return confirm('Supprimer ?')" style="color: #dc2626; margin-left: 10px;">Supprimer</a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($recompenses)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #666;">
                    <p>Aucune récompense configurée.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($tab === 'historique'): ?>
        <!-- Historique -->
        <div style="background: #f9fafb; border-radius: 10px; overflow: hidden;">
            <?php foreach ($transactions as $t): ?>
                <div class="transaction-row">
                    <div style="flex: 1;">
                        <strong><?php echo e($t['prenom'] . ' ' . $t['nom']); ?></strong>
                        <br><span style="color: #666; font-size: 12px;"><?php echo e($t['description'] ?: ucfirst($t['type_transaction'])); ?></span>
                    </div>
                    <div style="color: #666; font-size: 12px; margin-right: 20px;">
                        <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?>
                    </div>
                    <div class="points <?php echo $t['points'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $t['points'] >= 0 ? '+' : ''; ?><?php echo number_format($t['points']); ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($transactions)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    Aucune transaction pour le moment.
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($tab === 'parametres'): ?>
        <!-- Paramètres -->
        <form method="POST">
            <input type="hidden" name="save_settings" value="1">

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="fidelite_actif" <?php echo $settings['actif'] ? 'checked' : ''; ?>>
                    <span>Activer le programme de fidélité</span>
                </label>
            </div>

            <div class="section-title">Points attribués automatiquement</div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div class="form-group">
                    <label>Paiement cotisation</label>
                    <input type="number" name="points_cotisation" value="<?php echo $settings['points_cotisation']; ?>" min="0">
                    <p class="form-hint">Points gagnés à chaque cotisation payée</p>
                </div>
                <div class="form-group">
                    <label>Participation événement</label>
                    <input type="number" name="points_evenement" value="<?php echo $settings['points_evenement']; ?>" min="0">
                    <p class="form-hint">Points gagnés à chaque événement</p>
                </div>
                <div class="form-group">
                    <label>Bénévolat</label>
                    <input type="number" name="points_benevole" value="<?php echo $settings['points_benevole']; ?>" min="0">
                    <p class="form-hint">Points gagnés en tant que bénévole</p>
                </div>
                <div class="form-group">
                    <label>Parrainage</label>
                    <input type="number" name="points_parrainage" value="<?php echo $settings['points_parrainage']; ?>" min="0">
                    <p class="form-hint">Points gagnés pour un parrainage</p>
                </div>
                <div class="form-group">
                    <label>Anniversaire</label>
                    <input type="number" name="points_anniversaire" value="<?php echo $settings['points_anniversaire']; ?>" min="0">
                    <p class="form-hint">Points bonus le jour de l'anniversaire</p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter points -->
<div class="modal-overlay" id="addPointsModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Ajouter/Retirer des points</h3>
            <button class="modal-close" onclick="closeModal('addPointsModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="add_points" value="1">

                <div class="form-group">
                    <label>Membre</label>
                    <select name="user_id" id="pointsUserId" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($membres as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo e($m['prenom'] . ' ' . $m['nom']); ?> (<?php echo $m['points_fidelite']; ?> pts)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Points</label>
                        <input type="number" name="points" id="pointsAmount" required>
                        <p class="form-hint">Nombre positif pour ajouter, négatif pour retirer</p>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type_transaction">
                            <option value="bonus">Bonus</option>
                            <option value="ajustement">Ajustement</option>
                            <option value="gain">Gain</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="Raison de l'ajout/retrait">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPointsModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Récompense -->
<div class="modal-overlay" id="recompenseModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="recompenseModalTitle">Nouvelle récompense</h3>
            <button class="modal-close" onclick="closeModal('recompenseModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="save_recompense" value="1">
                <input type="hidden" name="edit_id" id="recompenseEditId" value="0">

                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="nom" id="recompenseNom" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="recompenseDesc" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Points requis *</label>
                        <input type="number" name="points_requis" id="recompensePoints" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type_recompense" id="recompenseType">
                            <option value="reduction">Réduction</option>
                            <option value="cadeau">Cadeau</option>
                            <option value="acces_vip">Accès VIP</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Valeur réduction (€)</label>
                        <input type="number" name="valeur_reduction" id="recompenseValeur" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Stock (-1 = illimité)</label>
                        <input type="number" name="stock" id="recompenseStock" value="-1" min="-1">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="actif" id="recompenseActif" checked>
                        <span>Actif</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('recompenseModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Actions membre -->
<div class="modal-overlay" id="memberActionsModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="memberName">Actions</h3>
            <button class="modal-close" onclick="closeModal('memberActionsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Solde actuel : <span class="points-badge" id="memberPoints">0 pts</span></p>

            <div style="margin-top: 20px;">
                <h4>Utiliser une récompense</h4>
                <form method="POST" id="useRewardForm">
                    <input type="hidden" name="use_reward" value="1">
                    <input type="hidden" name="user_id" id="rewardUserId">

                    <div class="form-group">
                        <select name="recompense_id" id="rewardSelect" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($recompenses as $r): ?>
                                <?php if ($r['actif']): ?>
                                    <option value="<?php echo $r['id']; ?>" data-points="<?php echo $r['points_requis']; ?>">
                                        <?php echo e($r['nom']); ?> (<?php echo number_format($r['points_requis']); ?> pts)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Utiliser</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showMemberActions(userId, name, points) {
    document.getElementById('memberName').textContent = name;
    document.getElementById('memberPoints').textContent = points + ' pts';
    document.getElementById('rewardUserId').value = userId;
    openModal('memberActionsModal');
}

function editRecompense(data) {
    document.getElementById('recompenseModalTitle').textContent = 'Modifier la récompense';
    document.getElementById('recompenseEditId').value = data.id;
    document.getElementById('recompenseNom').value = data.nom;
    document.getElementById('recompenseDesc').value = data.description || '';
    document.getElementById('recompensePoints').value = data.points_requis;
    document.getElementById('recompenseType').value = data.type_recompense;
    document.getElementById('recompenseValeur').value = data.valeur_reduction || 0;
    document.getElementById('recompenseStock').value = data.stock;
    document.getElementById('recompenseActif').checked = data.actif == 1;
    openModal('recompenseModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

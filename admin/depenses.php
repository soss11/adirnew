<?php
/**
 * Mini CRM - Gestion des Dépenses / Budget
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('gestionnaire');

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$evenement_id = (int)($_GET['evenement_id'] ?? 0);

// Catégories de dépenses
$categories = [
    'location' => ['label' => 'Location', 'icon' => '🏠', 'color' => '#1565c0'],
    'materiel' => ['label' => 'Matériel', 'icon' => '🔧', 'color' => '#7b1fa2'],
    'nourriture' => ['label' => 'Nourriture & Boissons', 'icon' => '🍽️', 'color' => '#388e3c'],
    'communication' => ['label' => 'Communication', 'icon' => '📢', 'color' => '#f57c00'],
    'artiste' => ['label' => 'Artiste / Intervenant', 'icon' => '🎤', 'color' => '#c62828'],
    'transport' => ['label' => 'Transport', 'icon' => '🚗', 'color' => '#00838f'],
    'autre' => ['label' => 'Autre', 'icon' => '📋', 'color' => '#546e7a']
];

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $depense_id = (int)($_POST['depense_id'] ?? 0);
        $depense_evenement_id = !empty($_POST['evenement_id']) ? (int)$_POST['evenement_id'] : null;
        $categorie = $_POST['categorie'] ?? 'autre';
        $description = trim($_POST['description'] ?? '');
        $montant = floatval($_POST['montant'] ?? 0);
        $date_depense = $_POST['date_depense'] ?? date('Y-m-d');
        $fournisseur = trim($_POST['fournisseur'] ?? '');

        if (empty($description) || $montant <= 0) {
            setFlashMessage('error', 'Veuillez remplir tous les champs obligatoires.');
        } else {
            // Upload justificatif
            $justificatif = null;
            if (!empty($_FILES['justificatif']['name'])) {
                $uploadDir = __DIR__ . '/../assets/uploads/justificatifs';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $ext = strtolower(pathinfo($_FILES['justificatif']['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

                if (in_array($ext, $allowed)) {
                    $filename = uniqid('just_') . '.' . $ext;
                    if (move_uploaded_file($_FILES['justificatif']['tmp_name'], "$uploadDir/$filename")) {
                        $justificatif = $filename;
                    }
                }
            }

            if ($depense_id > 0) {
                $sql = "UPDATE depenses SET evenement_id = ?, categorie = ?, description = ?, montant = ?, date_depense = ?, fournisseur = ?";
                $params = [$depense_evenement_id, $categorie, $description, $montant, $date_depense, $fournisseur];
                if ($justificatif) {
                    $sql .= ", justificatif = ?";
                    $params[] = $justificatif;
                }
                $sql .= " WHERE id = ?";
                $params[] = $depense_id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                setFlashMessage('success', 'Dépense modifiée.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO depenses (evenement_id, categorie, description, montant, date_depense, fournisseur, justificatif, cree_par) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$depense_evenement_id, $categorie, $description, $montant, $date_depense, $fournisseur, $justificatif, getCurrentUser()['id']]);
                setFlashMessage('success', 'Dépense ajoutée.');
            }

            header('Location: depenses.php' . ($depense_evenement_id ? '?evenement_id=' . $depense_evenement_id : ''));
            exit;
        }
    }

    if ($postAction === 'delete' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM depenses WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Dépense supprimée.');
        header('Location: depenses.php' . ($evenement_id ? '?evenement_id=' . $evenement_id : ''));
        exit;
    }
}

// Récupérer les événements
$evenements = $pdo->query("SELECT id, titre, date_debut FROM evenements ORDER BY date_debut DESC")->fetchAll();

// Construire la requête
$where = [];
$params = [];

if ($evenement_id > 0) {
    $where[] = "d.evenement_id = ?";
    $params[] = $evenement_id;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT d.*, e.titre as evenement_titre, u.nom as createur_nom
    FROM depenses d
    LEFT JOIN evenements e ON d.evenement_id = e.id
    LEFT JOIN users u ON d.cree_par = u.id
    $whereClause
    ORDER BY d.date_depense DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$depenses = $stmt->fetchAll();

// Calcul des totaux
$total = array_sum(array_column($depenses, 'montant'));
$totalParCategorie = [];
foreach ($depenses as $d) {
    $cat = $d['categorie'];
    $totalParCategorie[$cat] = ($totalParCategorie[$cat] ?? 0) + $d['montant'];
}

// Si événement sélectionné, calculer les recettes
$recettes = 0;
$benefice = 0;
if ($evenement_id > 0) {
    $stmt = $pdo->prepare("SELECT SUM(montant) as total FROM inscriptions WHERE evenement_id = ? AND statut IN ('confirme', 'present')");
    $stmt->execute([$evenement_id]);
    $recettes = (float)($stmt->fetch()['total'] ?? 0);
    $benefice = $recettes - $total;
}

$pageTitle = 'Dépenses & Budget';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.budget-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.budget-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 20px;
    border: 1px solid var(--border);
    text-align: center;
}
.budget-card .amount {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
}
.budget-card .label {
    color: var(--gray);
    font-size: 13px;
}
.budget-card.expenses .amount { color: var(--danger); }
.budget-card.income .amount { color: var(--success); }
.budget-card.profit .amount { color: var(--primary); }
.budget-card.loss .amount { color: var(--danger); }

.category-breakdown {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}
.category-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    background: var(--light);
}
</style>

<?php if ($action === 'list'): ?>
    <!-- Filtres -->
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body">
            <form method="GET" class="form-row" style="align-items: flex-end;">
                <div class="form-group" style="margin-bottom: 0; flex: 2;">
                    <label>Filtrer par événement</label>
                    <select name="evenement_id" onchange="this.form.submit()">
                        <option value="">Toutes les dépenses</option>
                        <option value="0" <?php echo isset($_GET['evenement_id']) && $_GET['evenement_id'] === '0' ? 'selected' : ''; ?>>Dépenses générales (sans événement)</option>
                        <?php foreach ($evenements as $ev): ?>
                            <option value="<?php echo $ev['id']; ?>" <?php echo $evenement_id == $ev['id'] ? 'selected' : ''; ?>>
                                <?php echo e($ev['titre']); ?> (<?php echo formatDate($ev['date_debut']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 0;">
                    <a href="?action=add<?php echo $evenement_id ? '&evenement_id=' . $evenement_id : ''; ?>" class="btn btn-primary">Ajouter une dépense</a>
                    <a href="rapport-financier.php" class="btn btn-secondary">Rapport annuel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Résumé budget -->
    <div class="budget-summary">
        <div class="budget-card expenses">
            <div class="amount"><?php echo number_format($total, 2, ',', ' '); ?> €</div>
            <div class="label">Total des dépenses</div>
        </div>
        <?php if ($evenement_id > 0): ?>
            <div class="budget-card income">
                <div class="amount"><?php echo number_format($recettes, 2, ',', ' '); ?> €</div>
                <div class="label">Recettes (inscriptions)</div>
            </div>
            <div class="budget-card <?php echo $benefice >= 0 ? 'profit' : 'loss'; ?>">
                <div class="amount"><?php echo ($benefice >= 0 ? '+' : '') . number_format($benefice, 2, ',', ' '); ?> €</div>
                <div class="label"><?php echo $benefice >= 0 ? 'Bénéfice' : 'Perte'; ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Répartition par catégorie -->
    <?php if (!empty($totalParCategorie)): ?>
        <div class="category-breakdown">
            <?php foreach ($totalParCategorie as $cat => $montant): ?>
                <span class="category-tag" style="border-left: 3px solid <?php echo $categories[$cat]['color'] ?? '#666'; ?>">
                    <?php echo $categories[$cat]['icon'] ?? ''; ?>
                    <?php echo $categories[$cat]['label'] ?? $cat; ?>:
                    <strong><?php echo number_format($montant, 2, ',', ' '); ?> €</strong>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Liste des dépenses -->
    <div class="card">
        <div class="card-header">
            <h3>Liste des dépenses</h3>
        </div>
        <div class="card-body">
            <?php if (empty($depenses)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💰</div>
                    <h3>Aucune dépense</h3>
                    <p>Commencez à enregistrer vos dépenses</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Catégorie</th>
                                <th>Événement</th>
                                <th>Montant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($depenses as $depense): ?>
                                <tr>
                                    <td><?php echo formatDate($depense['date_depense']); ?></td>
                                    <td>
                                        <strong><?php echo e($depense['description']); ?></strong>
                                        <?php if ($depense['fournisseur']): ?>
                                            <br><small class="text-gray"><?php echo e($depense['fournisseur']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="color: <?php echo $categories[$depense['categorie']]['color'] ?? '#666'; ?>">
                                            <?php echo $categories[$depense['categorie']]['icon'] ?? ''; ?>
                                            <?php echo $categories[$depense['categorie']]['label'] ?? $depense['categorie']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($depense['evenement_titre']): ?>
                                            <?php echo e($depense['evenement_titre']); ?>
                                        <?php else: ?>
                                            <span class="text-gray">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo number_format($depense['montant'], 2, ',', ' '); ?> €</strong></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($depense['justificatif']): ?>
                                                <a href="../assets/uploads/justificatifs/<?php echo e($depense['justificatif']); ?>" target="_blank" class="btn btn-sm btn-secondary" title="Voir justificatif">📎</a>
                                            <?php endif; ?>
                                            <a href="?action=edit&id=<?php echo $depense['id']; ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $depense['id']; ?>">
                                                <input type="hidden" name="evenement_id" value="<?php echo $evenement_id; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette dépense ?')">Supprimer</button>
                                            </form>
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

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <?php
    $depense = null;
    if ($action === 'edit' && $id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM depenses WHERE id = ?");
        $stmt->execute([$id]);
        $depense = $stmt->fetch();
        $evenement_id = $depense['evenement_id'] ?? 0;
    }
    ?>

    <div class="card">
        <div class="card-header">
            <h3><?php echo $depense ? 'Modifier la dépense' : 'Nouvelle dépense'; ?></h3>
            <a href="depenses.php<?php echo $evenement_id ? '?evenement_id=' . $evenement_id : ''; ?>" class="btn btn-secondary">Retour</a>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="depense_id" value="<?php echo $depense['id'] ?? 0; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Événement (optionnel)</label>
                        <select name="evenement_id">
                            <option value="">Dépense générale</option>
                            <?php foreach ($evenements as $ev): ?>
                                <option value="<?php echo $ev['id']; ?>" <?php echo ($depense['evenement_id'] ?? $evenement_id) == $ev['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($ev['titre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Catégorie *</label>
                        <select name="categorie" required>
                            <?php foreach ($categories as $key => $cat): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($depense['categorie'] ?? '') === $key ? 'selected' : ''; ?>>
                                    <?php echo $cat['icon']; ?> <?php echo $cat['label']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <input type="text" name="description" value="<?php echo e($depense['description'] ?? ''); ?>" required placeholder="Ex: Location salle des fêtes">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Montant (€) *</label>
                        <input type="number" name="montant" step="0.01" min="0.01" value="<?php echo $depense['montant'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="date_depense" value="<?php echo $depense['date_depense'] ?? date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Fournisseur / Prestataire</label>
                        <input type="text" name="fournisseur" value="<?php echo e($depense['fournisseur'] ?? ''); ?>" placeholder="Ex: Mairie de Lyon">
                    </div>
                    <div class="form-group">
                        <label>Justificatif (PDF, JPG, PNG)</label>
                        <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if ($depense['justificatif'] ?? null): ?>
                            <p class="form-hint">Fichier actuel: <a href="../assets/uploads/justificatifs/<?php echo e($depense['justificatif']); ?>" target="_blank"><?php echo e($depense['justificatif']); ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary"><?php echo $depense ? 'Modifier' : 'Ajouter'; ?></button>
                    <a href="depenses.php<?php echo $evenement_id ? '?evenement_id=' . $evenement_id : ''; ?>" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Mini CRM - Gestion de l'inventaire / Stock de matériel
 */

require_once __DIR__ . '/../includes/auth.php';
requireGestionnaire();

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    // Catégories
    if ($postAction === 'add_category') {
        $stmt = $pdo->prepare("INSERT INTO inventaire_categories (nom, description, icone, couleur) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['nom'],
            $_POST['description'] ?? '',
            $_POST['icone'] ?? '',
            $_POST['couleur'] ?? '#3498db'
        ]);
        setFlashMessage('success', 'Catégorie ajoutée.');
        header('Location: inventaire.php?action=categories');
        exit;
    }

    if ($postAction === 'delete_category') {
        $catId = intval($_POST['category_id']);
        $stmt = $pdo->prepare("DELETE FROM inventaire_categories WHERE id = ?");
        $stmt->execute([$catId]);
        setFlashMessage('success', 'Catégorie supprimée.');
        header('Location: inventaire.php?action=categories');
        exit;
    }

    // Items
    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'categorie_id' => $_POST['categorie_id'] ?: null,
            'nom' => $_POST['nom'],
            'description' => $_POST['description'] ?? '',
            'code_barre' => $_POST['code_barre'] ?? '',
            'reference' => $_POST['reference'] ?? '',
            'quantite' => intval($_POST['quantite']),
            'quantite_min' => intval($_POST['quantite_min'] ?? 0),
            'unite' => $_POST['unite'] ?? 'pièce',
            'emplacement' => $_POST['emplacement'] ?? '',
            'valeur_achat' => $_POST['valeur_achat'] ? floatval($_POST['valeur_achat']) : null,
            'date_achat' => $_POST['date_achat'] ?: null,
            'etat' => $_POST['etat'],
            'notes' => $_POST['notes'] ?? ''
        ];

        // Upload photo
        if (!empty($_FILES['photo']['name'])) {
            $uploadDir = __DIR__ . '/../assets/uploads/inventaire/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $filename = 'item_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                $data['photo'] = $filename;
            }
        }

        if ($postAction === 'create') {
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            $sql = "INSERT INTO inventaire_items (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));

            $itemId = $pdo->lastInsertId();

            // Enregistrer mouvement d'entrée initial
            if ($data['quantite'] > 0) {
                $stmt = $pdo->prepare("INSERT INTO inventaire_mouvements (item_id, type_mouvement, quantite, motif, created_by) VALUES (?, 'entree', ?, 'Stock initial', ?)");
                $stmt->execute([$itemId, $data['quantite'], getCurrentUserId()]);
            }

            setFlashMessage('success', 'Article ajouté à l\'inventaire.');
            header('Location: inventaire.php');
            exit;
        } else {
            $itemId = intval($_POST['item_id']);
            $sets = [];
            foreach ($data as $key => $value) {
                $sets[] = "$key = ?";
            }
            $sql = "UPDATE inventaire_items SET " . implode(', ', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([...array_values($data), $itemId]);
            setFlashMessage('success', 'Article mis à jour.');
            header('Location: inventaire.php?action=view&id=' . $itemId);
            exit;
        }
    }

    // Mouvement de stock
    if ($postAction === 'mouvement') {
        $itemId = intval($_POST['item_id']);
        $type = $_POST['type_mouvement'];
        $quantite = intval($_POST['quantite']);
        $motif = $_POST['motif'] ?? '';
        $eventId = $_POST['evenement_id'] ?: null;
        $userId = $_POST['user_id'] ?: null;

        // Enregistrer le mouvement
        $stmt = $pdo->prepare("INSERT INTO inventaire_mouvements (item_id, type_mouvement, quantite, evenement_id, user_id, motif, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$itemId, $type, $quantite, $eventId, $userId, $motif, getCurrentUserId()]);

        // Mettre à jour la quantité
        if (in_array($type, ['entree', 'retour', 'inventaire'])) {
            if ($type === 'inventaire') {
                // Ajustement d'inventaire = définir la quantité exacte
                $stmt = $pdo->prepare("UPDATE inventaire_items SET quantite = ? WHERE id = ?");
                $stmt->execute([$quantite, $itemId]);
            } else {
                $stmt = $pdo->prepare("UPDATE inventaire_items SET quantite = quantite + ? WHERE id = ?");
                $stmt->execute([$quantite, $itemId]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE inventaire_items SET quantite = quantite - ? WHERE id = ?");
            $stmt->execute([$quantite, $itemId]);
        }

        setFlashMessage('success', 'Mouvement enregistré.');
        header('Location: inventaire.php?action=view&id=' . $itemId);
        exit;
    }

    // Réservation
    if ($postAction === 'reservation') {
        $stmt = $pdo->prepare("INSERT INTO inventaire_reservations (item_id, user_id, evenement_id, quantite, date_debut, date_fin, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            intval($_POST['item_id']),
            intval($_POST['user_id']),
            $_POST['evenement_id'] ?: null,
            intval($_POST['quantite']),
            $_POST['date_debut'],
            $_POST['date_fin'],
            $_POST['notes'] ?? ''
        ]);
        setFlashMessage('success', 'Réservation créée.');
        header('Location: inventaire.php?action=view&id=' . intval($_POST['item_id']));
        exit;
    }

    if ($postAction === 'update_reservation') {
        $resId = intval($_POST['reservation_id']);
        $statut = $_POST['statut'];
        $itemId = intval($_POST['item_id']);

        $stmt = $pdo->prepare("UPDATE inventaire_reservations SET statut = ? WHERE id = ?");
        $stmt->execute([$statut, $resId]);
        setFlashMessage('success', 'Réservation mise à jour.');
        header('Location: inventaire.php?action=view&id=' . $itemId);
        exit;
    }

    if ($postAction === 'delete') {
        $itemId = intval($_POST['item_id']);
        $stmt = $pdo->prepare("DELETE FROM inventaire_items WHERE id = ?");
        $stmt->execute([$itemId]);
        setFlashMessage('success', 'Article supprimé.');
        header('Location: inventaire.php');
        exit;
    }
}

$pageTitle = 'Inventaire';
require_once __DIR__ . '/../includes/header.php';

// Catégories
$stmt = $pdo->query("SELECT * FROM inventaire_categories ORDER BY nom");
$categories = $stmt->fetchAll();
$categoriesById = [];
foreach ($categories as $cat) {
    $categoriesById[$cat['id']] = $cat;
}

$etats = [
    'neuf' => ['label' => 'Neuf', 'color' => 'success'],
    'bon' => ['label' => 'Bon état', 'color' => 'info'],
    'usage' => ['label' => 'Usagé', 'color' => 'warning'],
    'reparer' => ['label' => 'À réparer', 'color' => 'danger'],
    'hors_service' => ['label' => 'Hors service', 'color' => 'secondary']
];
?>

<?php if ($action === 'list'): ?>
    <?php
    // Filtres
    $filterCat = $_GET['cat'] ?? '';
    $filterEtat = $_GET['etat'] ?? '';
    $filterAlerte = isset($_GET['alerte']);
    $search = $_GET['q'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($filterCat) {
        $where[] = 'i.categorie_id = ?';
        $params[] = $filterCat;
    }
    if ($filterEtat) {
        $where[] = 'i.etat = ?';
        $params[] = $filterEtat;
    }
    if ($filterAlerte) {
        $where[] = 'i.quantite <= i.quantite_min';
    }
    if ($search) {
        $where[] = '(i.nom LIKE ? OR i.reference LIKE ? OR i.code_barre LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql = "SELECT i.*, c.nom as categorie_nom, c.couleur as categorie_couleur
            FROM inventaire_items i
            LEFT JOIN inventaire_categories c ON i.categorie_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Alertes de stock bas
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventaire_items WHERE quantite <= quantite_min AND quantite_min > 0");
    $nbAlertes = $stmt->fetchColumn();

    // Valeur totale
    $stmt = $pdo->query("SELECT SUM(quantite * COALESCE(valeur_achat, 0)) FROM inventaire_items WHERE actif = 1");
    $valeurTotale = $stmt->fetchColumn();
    ?>

    <?php if ($nbAlertes > 0): ?>
        <div class="alert alert-warning">
            <strong>Attention !</strong> <?php echo $nbAlertes; ?> article(s) en stock bas.
            <a href="?alerte=1">Voir les alertes</a>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Inventaire (<?php echo count($items); ?> articles)</h3>
            <div>
                <a href="?action=categories" class="btn btn-secondary">Catégories</a>
                <a href="?action=new" class="btn btn-primary">+ Nouvel article</a>
            </div>
        </div>
        <div class="card-body">
            <!-- Filtres -->
            <form method="GET" class="form-inline mb-4">
                <input type="text" name="q" class="form-control mr-2" placeholder="Rechercher..." value="<?php echo e($search); ?>">

                <select name="cat" class="form-control mr-2">
                    <option value="">Toutes catégories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filterCat == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo e($cat['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="etat" class="form-control mr-2">
                    <option value="">Tous états</option>
                    <?php foreach ($etats as $key => $etat): ?>
                        <option value="<?php echo $key; ?>" <?php echo $filterEtat === $key ? 'selected' : ''; ?>>
                            <?php echo e($etat['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="mr-2">
                    <input type="checkbox" name="alerte" <?php echo $filterAlerte ? 'checked' : ''; ?>> Stock bas
                </label>

                <button type="submit" class="btn btn-secondary">Filtrer</button>
                <a href="inventaire.php" class="btn btn-link">Reset</a>
            </form>

            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h4><?php echo count($items); ?></h4>
                            <small>Articles</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h4><?php echo array_sum(array_column($items, 'quantite')); ?></h4>
                            <small>Total unités</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h4><?php echo $nbAlertes; ?></h4>
                            <small>Alertes stock</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h4><?php echo number_format($valeurTotale, 0, ',', ' '); ?> EUR</h4>
                            <small>Valeur estimée</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste -->
            <?php if (empty($items)): ?>
                <p class="text-muted">Aucun article trouvé.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Catégorie</th>
                            <th>Quantité</th>
                            <th>État</th>
                            <th>Emplacement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $alerte = $item['quantite_min'] > 0 && $item['quantite'] <= $item['quantite_min']; ?>
                            <tr class="<?php echo $alerte ? 'table-warning' : ''; ?>">
                                <td>
                                    <strong><?php echo e($item['nom']); ?></strong>
                                    <?php if ($item['reference']): ?>
                                        <br><small class="text-muted">Réf: <?php echo e($item['reference']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['categorie_nom']): ?>
                                        <span class="badge" style="background-color: <?php echo e($item['categorie_couleur']); ?>; color: #fff;">
                                            <?php echo e($item['categorie_nom']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $item['quantite']; ?></strong> <?php echo e($item['unite']); ?>
                                    <?php if ($alerte): ?>
                                        <br><small class="text-danger">Stock bas (min: <?php echo $item['quantite_min']; ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $etats[$item['etat']]['color']; ?>">
                                        <?php echo e($etats[$item['etat']]['label']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($item['emplacement']); ?></td>
                                <td>
                                    <a href="?action=view&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info">Voir</a>
                                    <a href="?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'categories'): ?>
    <div class="card">
        <div class="card-header">
            <h3>Catégories d'inventaire</h3>
            <a href="inventaire.php" class="btn btn-secondary">Retour</a>
        </div>
        <div class="card-body">
            <!-- Ajouter -->
            <form method="POST" class="form-inline mb-4">
                <input type="hidden" name="action" value="add_category">
                <input type="text" name="nom" class="form-control mr-2" placeholder="Nom" required>
                <input type="text" name="icone" class="form-control mr-2" placeholder="Icone" style="width:80px">
                <input type="color" name="couleur" class="form-control mr-2" value="#3498db" style="width:60px">
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th>Articles</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat):
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventaire_items WHERE categorie_id = ?");
                        $stmt->execute([$cat['id']]);
                        $nbItems = $stmt->fetchColumn();
                    ?>
                        <tr>
                            <td>
                                <span class="badge" style="background-color: <?php echo e($cat['couleur']); ?>; color: #fff;">
                                    <?php echo e($cat['icone']); ?> <?php echo e($cat['nom']); ?>
                                </span>
                            </td>
                            <td><?php echo $nbItems; ?> article(s)</td>
                            <td>
                                <?php if ($nbItems == 0): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ?')">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
    <?php
    $item = null;
    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM inventaire_items WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
    }
    ?>

    <div class="card">
        <div class="card-header">
            <h3><?php echo $item ? 'Modifier l\'article' : 'Nouvel article'; ?></h3>
            <a href="inventaire.php" class="btn btn-secondary">Retour</a>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $item ? 'update' : 'create'; ?>">
                <?php if ($item): ?>
                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Nom de l'article *</label>
                        <input type="text" name="nom" class="form-control" required value="<?php echo e($item['nom'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Catégorie</label>
                        <select name="categorie_id" class="form-control">
                            <option value="">-- Sans catégorie --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($item['categorie_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($cat['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo e($item['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Référence</label>
                        <input type="text" name="reference" class="form-control" value="<?php echo e($item['reference'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Code-barres</label>
                        <input type="text" name="code_barre" class="form-control" value="<?php echo e($item['code_barre'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Emplacement</label>
                        <input type="text" name="emplacement" class="form-control" value="<?php echo e($item['emplacement'] ?? ''); ?>" placeholder="Ex: Armoire A, Étagère 2">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>Quantité *</label>
                        <input type="number" name="quantite" class="form-control" required min="0" value="<?php echo $item['quantite'] ?? 1; ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Unité</label>
                        <input type="text" name="unite" class="form-control" value="<?php echo e($item['unite'] ?? 'pièce'); ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label>Stock minimum (alerte)</label>
                        <input type="number" name="quantite_min" class="form-control" min="0" value="<?php echo $item['quantite_min'] ?? 0; ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label>État</label>
                        <select name="etat" class="form-control">
                            <?php foreach ($etats as $key => $etat): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($item['etat'] ?? 'bon') === $key ? 'selected' : ''; ?>>
                                    <?php echo e($etat['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Valeur d'achat (EUR)</label>
                        <input type="number" name="valeur_achat" class="form-control" step="0.01" min="0" value="<?php echo $item['valeur_achat'] ?? ''; ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Date d'achat</label>
                        <input type="date" name="date_achat" class="form-control" value="<?php echo $item['date_achat'] ?? ''; ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <?php if (!empty($item['photo'])): ?>
                            <small>Actuel: <?php echo e($item['photo']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?php echo e($item['notes'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="inventaire.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'view' && $id): ?>
    <?php
    $stmt = $pdo->prepare("SELECT i.*, c.nom as categorie_nom, c.couleur as categorie_couleur FROM inventaire_items i LEFT JOIN inventaire_categories c ON i.categorie_id = c.id WHERE i.id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        setFlashMessage('error', 'Article non trouvé.');
        header('Location: inventaire.php');
        exit;
    }

    // Mouvements
    $stmt = $pdo->prepare("
        SELECT m.*, u.prenom, u.nom, e.titre as event_titre, c.prenom as creator_prenom, c.nom as creator_nom
        FROM inventaire_mouvements m
        LEFT JOIN users u ON m.user_id = u.id
        LEFT JOIN users c ON m.created_by = c.id
        LEFT JOIN evenements e ON m.evenement_id = e.id
        WHERE m.item_id = ?
        ORDER BY m.date_mouvement DESC
        LIMIT 20
    ");
    $stmt->execute([$id]);
    $mouvements = $stmt->fetchAll();

    // Réservations
    $stmt = $pdo->prepare("
        SELECT r.*, u.prenom, u.nom, e.titre as event_titre
        FROM inventaire_reservations r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN evenements e ON r.evenement_id = e.id
        WHERE r.item_id = ? AND r.statut NOT IN ('terminee', 'annulee')
        ORDER BY r.date_debut
    ");
    $stmt->execute([$id]);
    $reservations = $stmt->fetchAll();

    // Membres et événements pour les formulaires
    $membres = $pdo->query("SELECT id, prenom, nom FROM users WHERE actif = 1 ORDER BY nom")->fetchAll();
    $evenements = $pdo->query("SELECT id, titre, date_debut FROM evenements WHERE date_debut >= CURDATE() ORDER BY date_debut")->fetchAll();

    $alerte = $item['quantite_min'] > 0 && $item['quantite'] <= $item['quantite_min'];
    ?>

    <div class="card mb-4">
        <div class="card-header">
            <h3>
                <?php echo e($item['nom']); ?>
                <?php if ($alerte): ?>
                    <span class="badge badge-danger">Stock bas !</span>
                <?php endif; ?>
            </h3>
            <div>
                <a href="?action=edit&id=<?php echo $id; ?>" class="btn btn-secondary">Modifier</a>
                <a href="inventaire.php" class="btn btn-secondary">Retour</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <table class="table table-bordered">
                        <tr>
                            <th>Catégorie</th>
                            <td>
                                <?php if ($item['categorie_nom']): ?>
                                    <span class="badge" style="background:<?php echo e($item['categorie_couleur']); ?>;color:#fff">
                                        <?php echo e($item['categorie_nom']); ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Quantité</th>
                            <td>
                                <strong style="font-size:1.5em;"><?php echo $item['quantite']; ?></strong> <?php echo e($item['unite']); ?>
                                <?php if ($item['quantite_min'] > 0): ?>
                                    <span class="text-muted">(min: <?php echo $item['quantite_min']; ?>)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>État</th>
                            <td>
                                <span class="badge badge-<?php echo $etats[$item['etat']]['color']; ?>">
                                    <?php echo e($etats[$item['etat']]['label']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Référence</th>
                            <td><?php echo e($item['reference'] ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Code-barres</th>
                            <td><?php echo e($item['code_barre'] ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Emplacement</th>
                            <td><?php echo e($item['emplacement'] ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Valeur</th>
                            <td><?php echo $item['valeur_achat'] ? number_format($item['valeur_achat'], 2, ',', ' ') . ' EUR' : '-'; ?></td>
                        </tr>
                        <tr>
                            <th>Date d'achat</th>
                            <td><?php echo $item['date_achat'] ? date('d/m/Y', strtotime($item['date_achat'])) : '-'; ?></td>
                        </tr>
                        <?php if ($item['description']): ?>
                            <tr>
                                <th>Description</th>
                                <td><?php echo nl2br(e($item['description'])); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($item['notes']): ?>
                            <tr>
                                <th>Notes</th>
                                <td><?php echo nl2br(e($item['notes'])); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="col-md-4">
                    <?php if ($item['photo']): ?>
                        <img src="<?php echo getBaseUrl(); ?>/assets/uploads/inventaire/<?php echo e($item['photo']); ?>" class="img-fluid rounded" alt="Photo">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mouvement de stock -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Enregistrer un mouvement</h3>
        </div>
        <div class="card-body">
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="mouvement">
                <input type="hidden" name="item_id" value="<?php echo $id; ?>">

                <select name="type_mouvement" class="form-control mr-2" required>
                    <option value="entree">+ Entrée</option>
                    <option value="sortie">- Sortie</option>
                    <option value="pret">- Prêt</option>
                    <option value="retour">+ Retour</option>
                    <option value="perte">- Perte</option>
                    <option value="inventaire">= Ajustement inventaire</option>
                </select>

                <input type="number" name="quantite" class="form-control mr-2" placeholder="Quantité" required min="1" style="width:100px">

                <select name="evenement_id" class="form-control mr-2">
                    <option value="">-- Événement --</option>
                    <?php foreach ($evenements as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo e($e['titre']); ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="motif" class="form-control mr-2" placeholder="Motif" style="width:200px">

                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </div>
    </div>

    <!-- Historique des mouvements -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Historique des mouvements</h3>
        </div>
        <div class="card-body">
            <?php if (empty($mouvements)): ?>
                <p class="text-muted">Aucun mouvement enregistré.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Quantité</th>
                            <th>Motif</th>
                            <th>Par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mouvements as $m):
                            $typesM = [
                                'entree' => ['+ Entrée', 'success'],
                                'sortie' => ['- Sortie', 'danger'],
                                'pret' => ['- Prêt', 'warning'],
                                'retour' => ['+ Retour', 'info'],
                                'perte' => ['- Perte', 'secondary'],
                                'inventaire' => ['= Ajustement', 'primary']
                            ];
                        ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $typesM[$m['type_mouvement']][1]; ?>">
                                        <?php echo $typesM[$m['type_mouvement']][0]; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $m['quantite']; ?></strong></td>
                                <td>
                                    <?php echo e($m['motif']); ?>
                                    <?php if ($m['event_titre']): ?>
                                        <br><small>Événement: <?php echo e($m['event_titre']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($m['creator_nom'] . ' ' . $m['creator_prenom']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Réservations -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Réservations en cours</h3>
        </div>
        <div class="card-body">
            <!-- Nouvelle réservation -->
            <form method="POST" class="form-inline mb-4">
                <input type="hidden" name="action" value="reservation">
                <input type="hidden" name="item_id" value="<?php echo $id; ?>">

                <select name="user_id" class="form-control mr-2" required>
                    <option value="">-- Membre --</option>
                    <?php foreach ($membres as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo e($m['nom'] . ' ' . $m['prenom']); ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="number" name="quantite" class="form-control mr-2" placeholder="Qté" required min="1" value="1" style="width:80px">

                <input type="date" name="date_debut" class="form-control mr-2" required>
                <span class="mr-2">au</span>
                <input type="date" name="date_fin" class="form-control mr-2" required>

                <select name="evenement_id" class="form-control mr-2">
                    <option value="">-- Événement --</option>
                    <?php foreach ($evenements as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo e($e['titre']); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary">Réserver</button>
            </form>

            <?php if (!empty($reservations)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Quantité</th>
                            <th>Période</th>
                            <th>Événement</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $r): ?>
                            <tr>
                                <td><?php echo e($r['nom'] . ' ' . $r['prenom']); ?></td>
                                <td><?php echo $r['quantite']; ?></td>
                                <td><?php echo date('d/m', strtotime($r['date_debut'])); ?> - <?php echo date('d/m/Y', strtotime($r['date_fin'])); ?></td>
                                <td><?php echo e($r['event_titre'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $statutRes = [
                                        'en_attente' => ['En attente', 'warning'],
                                        'confirmee' => ['Confirmée', 'success'],
                                        'en_cours' => ['En cours', 'info']
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $statutRes[$r['statut']][1]; ?>">
                                        <?php echo $statutRes[$r['statut']][0]; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="form-inline">
                                        <input type="hidden" name="action" value="update_reservation">
                                        <input type="hidden" name="reservation_id" value="<?php echo $r['id']; ?>">
                                        <input type="hidden" name="item_id" value="<?php echo $id; ?>">
                                        <select name="statut" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                                            <option value="en_attente" <?php echo $r['statut'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                            <option value="confirmee" <?php echo $r['statut'] === 'confirmee' ? 'selected' : ''; ?>>Confirmée</option>
                                            <option value="en_cours" <?php echo $r['statut'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                            <option value="terminee" <?php echo $r['statut'] === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                                            <option value="annulee" <?php echo $r['statut'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">Aucune réservation en cours.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Suppression -->
    <?php if (isAdmin()): ?>
        <div class="card border-danger">
            <div class="card-body">
                <form method="POST" onsubmit="return confirm('Supprimer cet article ?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item_id" value="<?php echo $id; ?>">
                    <button type="submit" class="btn btn-danger">Supprimer cet article</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

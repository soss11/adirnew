<?php
/**
 * Mini CRM - Gestion des documents membres
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('gestionnaire');

$membre_id = (int)($_GET['membre_id'] ?? 0);
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Types de documents
$typeDocuments = [
    'identite' => ['label' => 'Pièce d\'identité', 'icon' => '🪪'],
    'attestation' => ['label' => 'Attestation', 'icon' => '📜'],
    'justificatif' => ['label' => 'Justificatif', 'icon' => '📄'],
    'autre' => ['label' => 'Autre document', 'icon' => '📎']
];

// Vérifier que le membre existe
$membre = null;
if ($membre_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$membre_id]);
    $membre = $stmt->fetch();
    if (!$membre) {
        header('Location: membres.php');
        exit;
    }
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'upload' && $membre_id > 0) {
        $type_document = $_POST['type_document'] ?? 'autre';
        $nom_fichier = trim($_POST['nom_fichier'] ?? '');
        $date_expiration = !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if (empty($_FILES['fichier']['name'])) {
            setFlashMessage('error', 'Veuillez sélectionner un fichier.');
        } else {
            $uploadDir = __DIR__ . '/../assets/uploads/documents';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $file = $_FILES['fichier'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            if (!in_array($ext, $allowed)) {
                setFlashMessage('error', 'Type de fichier non autorisé (PDF, JPG, PNG, DOC uniquement).');
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                setFlashMessage('error', 'Fichier trop volumineux (max 5 Mo).');
            } else {
                $filename = 'doc_' . $membre_id . '_' . uniqid() . '.' . $ext;

                if (move_uploaded_file($file['tmp_name'], "$uploadDir/$filename")) {
                    if (empty($nom_fichier)) {
                        $nom_fichier = pathinfo($file['name'], PATHINFO_FILENAME);
                    }

                    $stmt = $pdo->prepare("INSERT INTO documents (user_id, type_document, nom_fichier, fichier, date_expiration, notes, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$membre_id, $type_document, $nom_fichier, $filename, $date_expiration, $notes, getCurrentUser()['id']]);

                    setFlashMessage('success', 'Document ajouté avec succès.');
                } else {
                    setFlashMessage('error', 'Erreur lors de l\'upload.');
                }
            }
        }

        header('Location: documents.php?membre_id=' . $membre_id);
        exit;
    }

    if ($postAction === 'delete' && $id > 0) {
        // Récupérer le document
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if ($doc) {
            // Supprimer le fichier
            $filepath = __DIR__ . '/../assets/uploads/documents/' . $doc['fichier'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Supprimer l'enregistrement
            $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
            setFlashMessage('success', 'Document supprimé.');
        }

        header('Location: documents.php?membre_id=' . $doc['user_id']);
        exit;
    }
}

// Récupérer les documents du membre
$documents = [];
if ($membre_id > 0) {
    $stmt = $pdo->prepare("SELECT d.*, u.nom as uploaded_by_nom FROM documents d LEFT JOIN users u ON d.uploaded_by = u.id WHERE d.user_id = ? ORDER BY d.created_at DESC");
    $stmt->execute([$membre_id]);
    $documents = $stmt->fetchAll();
}

$pageTitle = $membre ? 'Documents de ' . $membre['prenom'] . ' ' . $membre['nom'] : 'Documents';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$membre): ?>
    <div class="alert alert-error">Veuillez sélectionner un membre pour gérer ses documents.</div>
    <a href="membres.php" class="btn btn-primary">Voir les membres</a>
<?php else: ?>

<!-- Info membre -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;">
        <div style="display:flex;align-items:center;gap:15px;">
            <div style="width:50px;height:50px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:bold;">
                <?php echo e(substr($membre['prenom'] ?: $membre['nom'], 0, 1)); ?>
            </div>
            <div>
                <h3 style="margin:0;"><?php echo e($membre['prenom'] . ' ' . $membre['nom']); ?></h3>
                <p style="margin:0;color:var(--gray);font-size:14px;"><?php echo e($membre['email']); ?></p>
            </div>
        </div>
        <a href="membres.php?action=view&id=<?php echo $membre_id; ?>" class="btn btn-secondary">Retour au profil</a>
    </div>
</div>

<!-- Formulaire d'upload -->
<div class="card">
    <div class="card-header">
        <h3>Ajouter un document</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">

            <div class="form-row">
                <div class="form-group">
                    <label>Type de document *</label>
                    <select name="type_document" required>
                        <?php foreach ($typeDocuments as $key => $type): ?>
                            <option value="<?php echo $key; ?>"><?php echo $type['icon']; ?> <?php echo $type['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nom du document</label>
                    <input type="text" name="nom_fichier" placeholder="Ex: Carte d'identité 2024">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Fichier * (PDF, JPG, PNG, DOC - max 5Mo)</label>
                    <input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                </div>
                <div class="form-group">
                    <label>Date d'expiration (optionnel)</label>
                    <input type="date" name="date_expiration">
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="2" placeholder="Remarques éventuelles..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Ajouter le document</button>
        </form>
    </div>
</div>

<!-- Liste des documents -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>Documents (<?php echo count($documents); ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📁</div>
                <h3>Aucun document</h3>
                <p>Ajoutez des documents pour ce membre</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Nom</th>
                            <th>Date d'expiration</th>
                            <th>Ajouté le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc):
                            $type = $typeDocuments[$doc['type_document']] ?? $typeDocuments['autre'];
                            $expire = $doc['date_expiration'] && strtotime($doc['date_expiration']) < time();
                            $expireBientot = $doc['date_expiration'] && strtotime($doc['date_expiration']) < strtotime('+30 days') && !$expire;
                        ?>
                            <tr>
                                <td>
                                    <span style="font-size:18px;"><?php echo $type['icon']; ?></span>
                                    <?php echo $type['label']; ?>
                                </td>
                                <td>
                                    <strong><?php echo e($doc['nom_fichier']); ?></strong>
                                    <?php if ($doc['notes']): ?>
                                        <br><small class="text-gray"><?php echo e($doc['notes']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($doc['date_expiration']): ?>
                                        <?php if ($expire): ?>
                                            <span class="badge badge-danger">Expiré le <?php echo formatDate($doc['date_expiration']); ?></span>
                                        <?php elseif ($expireBientot): ?>
                                            <span class="badge badge-warning"><?php echo formatDate($doc['date_expiration']); ?></span>
                                        <?php else: ?>
                                            <?php echo formatDate($doc['date_expiration']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatDate($doc['created_at']); ?>
                                    <?php if ($doc['uploaded_by_nom']): ?>
                                        <br><small class="text-gray">par <?php echo e($doc['uploaded_by_nom']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="../assets/uploads/documents/<?php echo e($doc['fichier']); ?>" target="_blank" class="btn btn-sm btn-primary">Voir</a>
                                        <a href="../assets/uploads/documents/<?php echo e($doc['fichier']); ?>" download class="btn btn-sm btn-secondary">Télécharger</a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce document ?')">Supprimer</button>
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

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

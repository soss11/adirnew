<?php
/**
 * Mini CRM - Paramètres de l'association
 */

$pageTitle = 'Paramètres';
require_once __DIR__ . '/../includes/header.php';

requireRole('admin');

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asso_nom = trim($_POST['asso_nom'] ?? '');
    $asso_adresse = trim($_POST['asso_adresse'] ?? '');
    $asso_tel = trim($_POST['asso_tel'] ?? '');
    $asso_email = trim($_POST['asso_email'] ?? '');

    // Traitement du logo
    $logoUploaded = false;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/uploads/';

        // Créer le dossier s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $result = uploadImage($_FILES['logo'], $uploadDir);

        if ($result['success']) {
            // Supprimer l'ancien logo
            $oldLogo = getSetting('asso_logo');
            if ($oldLogo && file_exists($uploadDir . $oldLogo)) {
                unlink($uploadDir . $oldLogo);
            }

            setSetting('asso_logo', $result['filename']);
            $logoUploaded = true;
        } else {
            $error = $result['message'];
        }
    }

    // Supprimer le logo si demandé
    if (isset($_POST['delete_logo']) && $_POST['delete_logo'] === '1') {
        $uploadDir = __DIR__ . '/../assets/uploads/';
        $oldLogo = getSetting('asso_logo');
        if ($oldLogo && file_exists($uploadDir . $oldLogo)) {
            unlink($uploadDir . $oldLogo);
        }
        setSetting('asso_logo', '');
    }

    if (empty($error)) {
        // Sauvegarder les paramètres
        setSetting('asso_nom', $asso_nom);
        setSetting('asso_adresse', $asso_adresse);
        setSetting('asso_tel', $asso_tel);
        setSetting('asso_email', $asso_email);

        setFlashMessage('success', 'Les paramètres ont été enregistrés.');
        header('Location: settings.php');
        exit;
    }
}

// Rafraîchir les données
$asso = getAssociationSettings();
?>

<div class="card">
    <div class="card-header">
        <h3>Paramètres de l'association</h3>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="section-title">Logo</div>

            <div class="form-group">
                <div class="logo-preview" id="logo-preview">
                    <?php if (!empty($asso['logo'])): ?>
                        <img src="<?php echo getBaseUrl(); ?>/assets/uploads/<?php echo e($asso['logo']); ?>" alt="Logo actuel">
                    <?php else: ?>
                        <div class="logo-preview-placeholder">
                            <div style="font-size: 40px; margin-bottom: 10px;">&#128247;</div>
                            Aucun logo
                        </div>
                    <?php endif; ?>
                </div>

                <input type="file" id="logo-input" name="logo" accept="image/*" style="margin-bottom: 10px;">
                <p class="form-hint">Formats acceptés : JPG, PNG, GIF, WEBP. Taille max : 2 Mo.</p>

                <?php if (!empty($asso['logo'])): ?>
                    <label style="display: flex; align-items: center; margin-top: 10px; cursor: pointer;">
                        <input type="checkbox" name="delete_logo" value="1" style="margin-right: 8px;">
                        Supprimer le logo actuel
                    </label>
                <?php endif; ?>
            </div>

            <div class="section-title">Informations générales</div>

            <div class="form-group">
                <label for="asso_nom">Nom de l'association</label>
                <input type="text" id="asso_nom" name="asso_nom" value="<?php echo e($asso['nom']); ?>">
            </div>

            <div class="form-group">
                <label for="asso_adresse">Adresse</label>
                <textarea id="asso_adresse" name="asso_adresse"><?php echo e($asso['adresse']); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="asso_tel">Téléphone</label>
                    <input type="text" id="asso_tel" name="asso_tel" value="<?php echo e($asso['tel']); ?>">
                </div>
                <div class="form-group">
                    <label for="asso_email">Email</label>
                    <input type="email" id="asso_email" name="asso_email" value="<?php echo e($asso['email']); ?>">
                </div>
            </div>

            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Informations système</h3>
    </div>
    <div class="card-body">
        <table>
            <tr>
                <td style="width: 200px; font-weight: 500;">Version PHP</td>
                <td><?php echo phpversion(); ?></td>
            </tr>
            <tr>
                <td style="font-weight: 500;">Version MySQL</td>
                <td><?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></td>
            </tr>
            <tr>
                <td style="font-weight: 500;">Base de données</td>
                <td><?php echo DB_NAME; ?></td>
            </tr>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Mini CRM - Galerie photos par événement
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('gestionnaire');

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? '';
$eventId = (int)($_GET['event_id'] ?? 0);
$photoId = (int)($_GET['id'] ?? 0);

// Supprimer une photo
if ($action === 'delete' && $photoId > 0) {
    $stmt = $pdo->prepare("SELECT fichier FROM event_photos WHERE id = ?");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();

    if ($photo) {
        $filepath = __DIR__ . '/../assets/uploads/galerie/' . $photo['fichier'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        $stmt = $pdo->prepare("DELETE FROM event_photos WHERE id = ?");
        $stmt->execute([$photoId]);
        setFlashMessage('success', 'Photo supprimée.');
    }
    header('Location: galerie.php?event_id=' . $eventId);
    exit;
}

// Upload de photos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photos'])) {
    $eventId = (int)$_POST['evenement_id'];
    $uploadDir = __DIR__ . '/../assets/uploads/galerie/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadCount = 0;

    if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $fileCount = count($_FILES['photos']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['photos']['tmp_name'][$i];
                $originalName = $_FILES['photos']['name'][$i];

                // Vérifier le type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $filename = uniqid('photo_') . '.' . strtolower($extension);

                    if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                        $legende = trim($_POST['legendes'][$i] ?? '');

                        $stmt = $pdo->prepare("INSERT INTO event_photos (evenement_id, fichier, legende, uploaded_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$eventId, $filename, $legende, $currentUser['id']]);
                        $uploadCount++;
                    }
                }
            }
        }
    }

    if ($uploadCount > 0) {
        setFlashMessage('success', "$uploadCount photo(s) ajoutée(s) avec succès.");
    } else {
        setFlashMessage('error', 'Aucune photo n\'a pu être uploadée.');
    }
    header('Location: galerie.php?event_id=' . $eventId);
    exit;
}

// Mettre à jour légende
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_legende'])) {
    $photoId = (int)$_POST['photo_id'];
    $legende = trim($_POST['legende'] ?? '');
    $eventId = (int)$_POST['event_id'];

    $stmt = $pdo->prepare("UPDATE event_photos SET legende = ? WHERE id = ?");
    $stmt->execute([$legende, $photoId]);

    setFlashMessage('success', 'Légende mise à jour.');
    header('Location: galerie.php?event_id=' . $eventId);
    exit;
}

$pageTitle = 'Galerie Photos';
require_once __DIR__ . '/../includes/header.php';

// Récupérer les événements terminés ou avec photos
$evenements = $pdo->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM event_photos p WHERE p.evenement_id = e.id) as nb_photos
    FROM evenements e
    ORDER BY e.date_debut DESC
")->fetchAll();

// Si un événement est sélectionné, récupérer ses photos
$photos = [];
$selectedEvent = null;
if ($eventId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt->execute([$eventId]);
    $selectedEvent = $stmt->fetch();

    if ($selectedEvent) {
        $stmt = $pdo->prepare("SELECT p.*, u.nom as uploader_nom, u.prenom as uploader_prenom
                               FROM event_photos p
                               LEFT JOIN users u ON p.uploaded_by = u.id
                               WHERE p.evenement_id = ?
                               ORDER BY p.ordre, p.created_at");
        $stmt->execute([$eventId]);
        $photos = $stmt->fetchAll();
    }
}
?>

<style>
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.gallery-item {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background: #fff;
}
.gallery-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    cursor: pointer;
}
.gallery-item .actions {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    gap: 5px;
}
.gallery-item .actions a {
    background: rgba(255,255,255,0.9);
    color: #333;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 14px;
}
.gallery-item .actions a:hover {
    background: #fff;
}
.gallery-item .legende {
    padding: 10px;
    font-size: 13px;
    color: #666;
    border-top: 1px solid #eee;
}
.event-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 10px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}
.event-card:hover, .event-card.active {
    background: #e8f0fe;
    border-left: 3px solid var(--primary);
}
.event-card .photo-count {
    background: var(--primary);
    color: #fff;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
}
.upload-zone {
    border: 2px dashed #ddd;
    border-radius: 10px;
    padding: 40px;
    text-align: center;
    margin-bottom: 20px;
    background: #fafafa;
}
.upload-zone:hover {
    border-color: var(--primary);
    background: #f0f7ff;
}
.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.lightbox.active {
    display: flex;
}
.lightbox img {
    max-width: 90%;
    max-height: 90%;
    border-radius: 8px;
}
.lightbox .close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: #fff;
    font-size: 40px;
    cursor: pointer;
}
</style>

<div class="card">
    <div class="card-header">
        <h3>Galerie Photos des Événements</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 30px;">
            <!-- Liste des événements -->
            <div>
                <h4 style="margin-bottom: 15px;">Événements</h4>
                <?php foreach ($evenements as $event): ?>
                    <a href="?event_id=<?php echo $event['id']; ?>" class="event-card <?php echo $eventId == $event['id'] ? 'active' : ''; ?>" style="text-decoration: none; color: inherit;">
                        <div style="flex: 1;">
                            <div style="font-weight: 500;"><?php echo e($event['titre']); ?></div>
                            <div style="font-size: 12px; color: #666;"><?php echo date('d/m/Y', strtotime($event['date_debut'])); ?></div>
                        </div>
                        <?php if ($event['nb_photos'] > 0): ?>
                            <span class="photo-count"><?php echo $event['nb_photos']; ?> photo<?php echo $event['nb_photos'] > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>

                <?php if (empty($evenements)): ?>
                    <p style="color: #666;">Aucun événement disponible.</p>
                <?php endif; ?>
            </div>

            <!-- Zone de galerie -->
            <div>
                <?php if ($selectedEvent): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h4 style="margin: 0;"><?php echo e($selectedEvent['titre']); ?></h4>
                            <div style="color: #666; font-size: 14px;"><?php echo date('d/m/Y', strtotime($selectedEvent['date_debut'])); ?> - <?php echo count($photos); ?> photo(s)</div>
                        </div>
                        <button onclick="openModal('uploadModal')" class="btn btn-primary btn-sm">+ Ajouter des photos</button>
                    </div>

                    <?php if (empty($photos)): ?>
                        <div class="upload-zone" onclick="openModal('uploadModal')">
                            <div style="font-size: 48px; color: #ccc;">&#128247;</div>
                            <p style="color: #666;">Aucune photo pour cet événement.<br>Cliquez pour ajouter des photos.</p>
                        </div>
                    <?php else: ?>
                        <div class="gallery-grid">
                            <?php foreach ($photos as $photo): ?>
                                <div class="gallery-item">
                                    <img src="<?php echo getBaseUrl(); ?>/assets/uploads/galerie/<?php echo e($photo['fichier']); ?>"
                                         onclick="openLightbox(this.src)" alt="">
                                    <div class="actions">
                                        <a href="#" onclick="editLegende(<?php echo $photo['id']; ?>, '<?php echo e(addslashes($photo['legende'] ?? '')); ?>'); return false;" title="Modifier">&#9998;</a>
                                        <a href="?action=delete&id=<?php echo $photo['id']; ?>&event_id=<?php echo $eventId; ?>"
                                           onclick="return confirm('Supprimer cette photo ?')" title="Supprimer" style="color: #dc2626;">&#128465;</a>
                                    </div>
                                    <?php if ($photo['legende']): ?>
                                        <div class="legende"><?php echo e($photo['legende']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: #666;">
                        <div style="font-size: 64px;">&#128247;</div>
                        <h4>Sélectionnez un événement</h4>
                        <p>Choisissez un événement dans la liste pour voir ou ajouter des photos.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Upload -->
<?php if ($selectedEvent): ?>
<div class="modal-overlay" id="uploadModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Ajouter des photos</h3>
            <button class="modal-close" onclick="closeModal('uploadModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="upload_photos" value="1">
                <input type="hidden" name="evenement_id" value="<?php echo $selectedEvent['id']; ?>">

                <div class="form-group">
                    <label>Sélectionner des photos</label>
                    <input type="file" name="photos[]" id="photoInput" multiple accept="image/*" required onchange="previewPhotos(this)">
                    <p class="form-hint">Vous pouvez sélectionner plusieurs photos à la fois.</p>
                </div>

                <div id="previewContainer" style="display: none;">
                    <label>Aperçu et légendes</label>
                    <div id="photoPreviews" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 10px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Uploader</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Légende -->
<div class="modal-overlay" id="legendeModal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Modifier la légende</h3>
            <button class="modal-close" onclick="closeModal('legendeModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="update_legende" value="1">
                <input type="hidden" name="photo_id" id="legendePhotoId">
                <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">

                <div class="form-group">
                    <label>Légende</label>
                    <input type="text" name="legende" id="legendeInput" maxlength="255">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('legendeModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="close">&times;</span>
    <img src="" id="lightboxImg" alt="">
</div>

<script>
function previewPhotos(input) {
    var container = document.getElementById('previewContainer');
    var previews = document.getElementById('photoPreviews');
    previews.innerHTML = '';

    if (input.files.length > 0) {
        container.style.display = 'block';

        Array.from(input.files).forEach(function(file, index) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var div = document.createElement('div');
                div.style.cssText = 'background:#f5f5f5;border-radius:8px;overflow:hidden;';
                div.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100px;object-fit:cover;">' +
                               '<input type="text" name="legendes[]" placeholder="Légende..." style="width:100%;border:none;padding:8px;font-size:12px;">';
                previews.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    } else {
        container.style.display = 'none';
    }
}

function editLegende(photoId, currentLegende) {
    document.getElementById('legendePhotoId').value = photoId;
    document.getElementById('legendeInput').value = currentLegende;
    openModal('legendeModal');
}

function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('active');
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

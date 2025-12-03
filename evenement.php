<?php
/**
 * Mini CRM - Page publique d'un événement (accessible sans connexion)
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit;
}

// Récupérer l'événement
$stmt = $pdo->prepare("SELECT e.*,
    (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut != 'annule') as nb_inscrits
    FROM evenements e
    WHERE e.id = ? AND e.statut IN ('publie', 'complet')");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: index.php');
    exit;
}

// Récupérer les infos de l'association
$asso = getAssociationSettings();

$eventTypes = [
    'spectacle' => ['label' => 'Spectacle', 'color' => '#e91e63', 'icon' => '🎭'],
    'soiree' => ['label' => 'Soirée', 'color' => '#9c27b0', 'icon' => '🎉'],
    'tombola' => ['label' => 'Tombola', 'color' => '#ff9800', 'icon' => '🎟️'],
    'atelier' => ['label' => 'Atelier', 'color' => '#4caf50', 'icon' => '🎨'],
    'reunion' => ['label' => 'Réunion', 'color' => '#2196f3', 'icon' => '👥'],
    'autre' => ['label' => 'Autre', 'color' => '#607d8b', 'icon' => '📅']
];

$type = $eventTypes[$event['type']] ?? $eventTypes['autre'];

// URL de la page
$pageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
           '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Texte de partage
$shareTitle = $event['titre'] . ' - ' . ($asso['nom'] ?? 'Événement');
$shareText = $event['titre'] . ' le ' . date('d/m/Y à H:i', strtotime($event['date_debut']));
if ($event['lieu']) {
    $shareText .= ' à ' . $event['lieu'];
}

// URLs de partage
$shareUrls = [
    'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($pageUrl),
    'twitter' => 'https://twitter.com/intent/tweet?text=' . urlencode($shareText) . '&url=' . urlencode($pageUrl),
    'whatsapp' => 'https://wa.me/?text=' . urlencode($shareText . ' ' . $pageUrl),
    'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($pageUrl),
    'email' => 'mailto:?subject=' . rawurlencode($shareTitle) . '&body=' . rawurlencode($shareText . "\n\n" . $pageUrl)
];

// Formater la date
$dateDebut = new DateTime($event['date_debut']);
$moisFr = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$joursFr = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$dateFormatee = ucfirst($joursFr[(int)$dateDebut->format('w')]) . ' ' .
               $dateDebut->format('d') . ' ' .
               $moisFr[(int)$dateDebut->format('n')] . ' ' .
               $dateDebut->format('Y') . ' à ' .
               $dateDebut->format('H:i');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($event['titre']); ?> - <?php echo e($asso['nom'] ?? 'Événement'); ?></title>

    <!-- Meta tags pour le partage -->
    <meta property="og:title" content="<?php echo e($shareTitle); ?>">
    <meta property="og:description" content="<?php echo e($shareText); ?>">
    <meta property="og:type" content="event">
    <meta property="og:url" content="<?php echo e($pageUrl); ?>">
    <?php if ($event['affiche']): ?>
    <meta property="og:image" content="<?php echo e(dirname($pageUrl) . '/assets/uploads/' . $event['affiche']); ?>">
    <?php endif; ?>

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo e($shareTitle); ?>">
    <meta name="twitter:description" content="<?php echo e($shareText); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .event-card {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .event-header {
            background: <?php echo $type['color']; ?>;
            color: #fff;
            padding: 30px;
            text-align: center;
        }

        .event-type {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .event-type-icon {
            font-size: 24px;
            margin-right: 8px;
        }

        .event-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .event-asso {
            opacity: 0.9;
            font-size: 16px;
        }

        .event-body {
            padding: 30px;
        }

        .event-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .info-icon {
            width: 50px;
            height: 50px;
            background: #f5f5f5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .info-content h4 {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-content p {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }

        .event-description {
            background: #f9f9f9;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .event-description h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }

        .event-description p {
            color: #666;
            line-height: 1.7;
        }

        .event-prices {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .price-box {
            flex: 1;
            min-width: 150px;
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
            color: #fff;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .price-box.secondary {
            background: #f5f5f5;
            color: #333;
        }

        .price-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .price-amount {
            font-size: 28px;
            font-weight: 700;
        }

        .event-status {
            text-align: center;
            padding: 20px;
            background: #f0f4ff;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .event-status.complet {
            background: #fff0f0;
        }

        .places-info {
            font-size: 18px;
            color: #333;
        }

        .places-info strong {
            color: <?php echo $type['color']; ?>;
        }

        /* Affiche de l'événement */
        .event-poster {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
        }

        .event-poster-container {
            position: relative;
            overflow: hidden;
        }

        .event-poster-container::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(transparent, rgba(0,0,0,0.3));
            pointer-events: none;
        }

        /* Boutons de partage */
        .share-section {
            border-top: 1px solid #eee;
            padding-top: 25px;
        }

        .share-section h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }

        .share-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .share-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .share-btn.facebook { background: #1877f2; }
        .share-btn.twitter { background: #1da1f2; }
        .share-btn.whatsapp { background: #25d366; }
        .share-btn.linkedin { background: #0077b5; }
        .share-btn.email { background: #666; }
        .share-btn.copy { background: #333; cursor: pointer; border: none; }

        .share-btn svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* QR Code */
        .qr-section {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }

        .qr-section h4 {
            font-size: 14px;
            color: #888;
            margin-bottom: 15px;
        }

        #qrcode {
            display: inline-block;
            padding: 15px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #fff;
            text-decoration: none;
            opacity: 0.9;
        }

        .back-link a:hover {
            opacity: 1;
            text-decoration: underline;
        }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #333;
            color: #fff;
            padding: 15px 30px;
            border-radius: 10px;
            opacity: 0;
            transition: all 0.3s;
            z-index: 1000;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        @media (max-width: 600px) {
            .event-title {
                font-size: 24px;
            }

            .share-btn span {
                display: none;
            }

            .share-btn {
                padding: 12px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="event-card">
        <div class="event-header">
            <div class="event-type">
                <span class="event-type-icon"><?php echo $type['icon']; ?></span>
                <?php echo $type['label']; ?>
            </div>
            <h1 class="event-title"><?php echo e($event['titre']); ?></h1>
            <p class="event-asso"><?php echo e($asso['nom'] ?? ''); ?></p>
        </div>

        <?php if ($event['affiche']): ?>
        <div class="event-poster-container">
            <img src="assets/uploads/<?php echo e($event['affiche']); ?>" alt="<?php echo e($event['titre']); ?>" class="event-poster">
        </div>
        <?php endif; ?>

        <div class="event-body">
            <div class="event-info">
                <div class="info-item">
                    <div class="info-icon">📅</div>
                    <div class="info-content">
                        <h4>Date</h4>
                        <p><?php echo $dateFormatee; ?></p>
                    </div>
                </div>

                <?php if ($event['lieu']): ?>
                <div class="info-item">
                    <div class="info-icon">📍</div>
                    <div class="info-content">
                        <h4>Lieu</h4>
                        <p><?php echo e($event['lieu']); ?></p>
                        <?php if ($event['adresse']): ?>
                            <p style="font-size:13px;color:#888;margin-top:3px;"><?php echo e($event['adresse']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($event['places_max'] > 0): ?>
                <div class="info-item">
                    <div class="info-icon">👥</div>
                    <div class="info-content">
                        <h4>Places</h4>
                        <p><?php echo $event['nb_inscrits']; ?> / <?php echo $event['places_max']; ?> inscrit(s)</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($event['description']): ?>
            <div class="event-description">
                <h3>Description</h3>
                <p><?php echo nl2br(e($event['description'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($event['prix_membre'] > 0 || $event['prix_non_membre'] > 0): ?>
            <div class="event-prices">
                <?php if ($event['prix_membre'] > 0): ?>
                <div class="price-box">
                    <div class="price-label">Tarif membre</div>
                    <div class="price-amount"><?php echo number_format($event['prix_membre'], 2, ',', ' '); ?> €</div>
                </div>
                <?php endif; ?>
                <?php if ($event['prix_non_membre'] > 0): ?>
                <div class="price-box secondary">
                    <div class="price-label">Tarif non-membre</div>
                    <div class="price-amount"><?php echo number_format($event['prix_non_membre'], 2, ',', ' '); ?> €</div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($event['statut'] === 'complet'): ?>
            <div class="event-status complet">
                <p class="places-info"><strong>Complet !</strong> Cet événement affiche complet.</p>
                <a href="inscription-evenement.php?id=<?php echo $id; ?>" style="display:inline-block;margin-top:15px;padding:12px 30px;background:#6366f1;color:#fff;border-radius:10px;text-decoration:none;font-weight:600;">S'inscrire sur la liste d'attente</a>
            </div>
            <?php elseif ($event['places_max'] > 0): ?>
            <div class="event-status">
                <p class="places-info">
                    <strong><?php echo $event['places_max'] - $event['nb_inscrits']; ?></strong> place(s) restante(s)
                </p>
                <a href="inscription-evenement.php?id=<?php echo $id; ?>" style="display:inline-block;margin-top:15px;padding:14px 40px;background:linear-gradient(135deg, #1565c0, #0d47a1);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:16px;">Je m'inscris</a>
            </div>
            <?php else: ?>
            <div class="event-status">
                <a href="inscription-evenement.php?id=<?php echo $id; ?>" style="display:inline-block;padding:14px 40px;background:linear-gradient(135deg, #1565c0, #0d47a1);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:16px;">Je m'inscris</a>
            </div>
            <?php endif; ?>

            <!-- Partage -->
            <div class="share-section">
                <h3>Partager cet événement</h3>
                <div class="share-buttons">
                    <a href="<?php echo $shareUrls['facebook']; ?>" target="_blank" class="share-btn facebook" title="Partager sur Facebook">
                        <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        <span>Facebook</span>
                    </a>
                    <a href="<?php echo $shareUrls['twitter']; ?>" target="_blank" class="share-btn twitter" title="Partager sur Twitter">
                        <svg viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                        <span>Twitter</span>
                    </a>
                    <a href="<?php echo $shareUrls['whatsapp']; ?>" target="_blank" class="share-btn whatsapp" title="Partager sur WhatsApp">
                        <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        <span>WhatsApp</span>
                    </a>
                    <a href="<?php echo $shareUrls['email']; ?>" class="share-btn email" title="Partager par email">
                        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        <span>Email</span>
                    </a>
                    <button onclick="copyLink()" class="share-btn copy" title="Copier le lien">
                        <svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                        <span>Copier</span>
                    </button>
                </div>
            </div>

            <!-- QR Code -->
            <div class="qr-section">
                <h4>Scanner pour accéder à l'événement</h4>
                <div id="qrcode"></div>
            </div>
        </div>
    </div>

    <div class="back-link">
        <a href="index.php">← Retour à l'accueil</a>
    </div>
</div>

<div class="toast" id="toast">Lien copié !</div>

<!-- QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
// Générer le QR Code
new QRCode(document.getElementById("qrcode"), {
    text: "<?php echo e($pageUrl); ?>",
    width: 150,
    height: 150,
    colorDark: "#333",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});

// Copier le lien
function copyLink() {
    navigator.clipboard.writeText("<?php echo e($pageUrl); ?>").then(function() {
        showToast();
    });
}

function showToast() {
    var toast = document.getElementById('toast');
    toast.classList.add('show');
    setTimeout(function() {
        toast.classList.remove('show');
    }, 2000);
}
</script>

</body>
</html>

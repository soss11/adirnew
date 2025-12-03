<?php
/**
 * Mini CRM - Génération de billet PDF avec QR code
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$code = $_GET['code'] ?? '';
$format = $_GET['format'] ?? 'html'; // html ou pdf

if (empty($code)) {
    header('Location: index.php');
    exit;
}

// Récupérer l'inscription
$stmt = $pdo->prepare("SELECT i.*, e.titre, e.date_debut, e.date_fin, e.lieu, e.adresse, e.type,
    COALESCE(CONCAT(u.prenom, ' ', u.nom), CONCAT(i.prenom_participant, ' ', i.nom_participant)) as participant_nom,
    COALESCE(u.email, i.email_participant) as participant_email
    FROM inscriptions i
    JOIN evenements e ON i.evenement_id = e.id
    LEFT JOIN users u ON i.user_id = u.id
    WHERE i.ticket_code = ?");
$stmt->execute([$code]);
$ticket = $stmt->fetch();

if (!$ticket) {
    die('Billet non trouvé');
}

$asso = getAssociationSettings();

// Types d'événements
$eventTypes = [
    'spectacle' => ['label' => 'Spectacle', 'color' => '#e91e63', 'icon' => '🎭'],
    'soiree' => ['label' => 'Soirée', 'color' => '#9c27b0', 'icon' => '🎉'],
    'tombola' => ['label' => 'Tombola', 'color' => '#ff9800', 'icon' => '🎟️'],
    'atelier' => ['label' => 'Atelier', 'color' => '#4caf50', 'icon' => '🎨'],
    'reunion' => ['label' => 'Réunion', 'color' => '#2196f3', 'icon' => '👥'],
    'autre' => ['label' => 'Autre', 'color' => '#607d8b', 'icon' => '📅']
];
$type = $eventTypes[$ticket['type']] ?? $eventTypes['autre'];

// Formater la date
$dateDebut = new DateTime($ticket['date_debut']);
$moisFr = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
$joursFr = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

// URL du QR code (vers la page de check-in)
$qrData = $code;
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

// Statut
$statuts = [
    'en_attente' => ['label' => 'En attente de paiement', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
    'confirme' => ['label' => 'Confirmé', 'color' => '#16a34a', 'bg' => '#dcfce7'],
    'liste_attente' => ['label' => 'Liste d\'attente', 'color' => '#6366f1', 'bg' => '#e0e7ff'],
    'present' => ['label' => 'Présent', 'color' => '#16a34a', 'bg' => '#dcfce7'],
    'annule' => ['label' => 'Annulé', 'color' => '#dc2626', 'bg' => '#fee2e2']
];
$statut = $statuts[$ticket['statut']] ?? $statuts['en_attente'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billet - <?php echo e($ticket['titre']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .ticket {
            background: #fff;
            width: 100%;
            max-width: 450px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            position: relative;
        }

        .ticket-header {
            background: <?php echo $type['color']; ?>;
            color: #fff;
            padding: 24px;
            text-align: center;
            position: relative;
        }
        .ticket-header::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 20px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><path d="M0,20 L0,0 C25,0 25,10 50,10 C75,10 75,0 100,0 L100,20 Z" fill="%23ffffff"/></svg>') repeat-x;
            background-size: 50px 20px;
        }

        .ticket-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 28px;
        }
        .ticket-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        .ticket-org { font-size: 14px; opacity: 0.9; margin-bottom: 4px; }
        .ticket-title { font-size: 22px; font-weight: 700; }

        .ticket-body { padding: 30px 24px; }

        .ticket-qr {
            text-align: center;
            margin-bottom: 20px;
        }
        .ticket-qr img {
            width: 150px;
            height: 150px;
            border-radius: 8px;
        }
        .ticket-code {
            font-family: monospace;
            font-size: 18px;
            font-weight: 700;
            color: #1565c0;
            margin-top: 8px;
            letter-spacing: 2px;
        }

        .ticket-info {
            border-top: 2px dashed #e2e8f0;
            padding-top: 20px;
        }
        .ticket-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .ticket-row:last-child { border-bottom: none; }
        .ticket-label { color: #64748b; font-size: 13px; }
        .ticket-value { font-weight: 600; color: #1a1a2e; }

        .ticket-status {
            text-align: center;
            padding: 12px;
            margin-top: 16px;
            border-radius: 8px;
            font-weight: 600;
            background: <?php echo $statut['bg']; ?>;
            color: <?php echo $statut['color']; ?>;
        }

        .ticket-footer {
            background: #f8fafc;
            padding: 16px 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }

        .ticket-places {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: <?php echo $type['color']; ?>;
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 20px;
            font-weight: 700;
        }

        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: #1565c0; color: #fff; }
        .btn-primary:hover { background: #0d47a1; }
        .btn-secondary { background: #e2e8f0; color: #64748b; }

        @media print {
            body { background: #fff; padding: 0; }
            .ticket { box-shadow: none; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="ticket-header">
            <div class="ticket-logo">
                <?php if (!empty($asso['logo'])): ?>
                    <img src="assets/uploads/<?php echo e($asso['logo']); ?>" alt="Logo">
                <?php else: ?>
                    <?php echo $type['icon']; ?>
                <?php endif; ?>
            </div>
            <div class="ticket-org"><?php echo e($asso['nom']); ?></div>
            <div class="ticket-title"><?php echo e($ticket['titre']); ?></div>
        </div>

        <div class="ticket-body">
            <div class="ticket-qr">
                <img src="<?php echo $qrUrl; ?>" alt="QR Code">
                <div class="ticket-code"><?php echo e($ticket['ticket_code']); ?></div>
            </div>

            <div class="ticket-info">
                <div class="ticket-row">
                    <span class="ticket-label">Participant</span>
                    <span class="ticket-value"><?php echo e($ticket['participant_nom']); ?></span>
                </div>
                <div class="ticket-row">
                    <span class="ticket-label">Date</span>
                    <span class="ticket-value">
                        <?php echo $joursFr[(int)$dateDebut->format('w')]; ?>
                        <?php echo $dateDebut->format('d'); ?>
                        <?php echo $moisFr[(int)$dateDebut->format('n')]; ?>
                        <?php echo $dateDebut->format('Y'); ?>
                        à <?php echo $dateDebut->format('H:i'); ?>
                    </span>
                </div>
                <?php if ($ticket['lieu']): ?>
                <div class="ticket-row">
                    <span class="ticket-label">Lieu</span>
                    <span class="ticket-value"><?php echo e($ticket['lieu']); ?></span>
                </div>
                <?php endif; ?>
                <div class="ticket-row">
                    <span class="ticket-label">Places</span>
                    <span class="ticket-value">
                        <span class="ticket-places"><?php echo $ticket['nombre_places']; ?></span>
                    </span>
                </div>
                <?php if ($ticket['montant'] > 0): ?>
                <div class="ticket-row">
                    <span class="ticket-label">Montant</span>
                    <span class="ticket-value"><?php echo number_format($ticket['montant'], 2, ',', ' '); ?> €</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="ticket-status">
                <?php echo $statut['label']; ?>
            </div>
        </div>

        <div class="ticket-footer">
            <p>Présentez ce billet (imprimé ou sur téléphone) à l'entrée.</p>
            <?php if ($asso['email']): ?>
                <p style="margin-top: 4px;"><?php echo e($asso['email']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="actions">
        <button onclick="window.print()" class="btn btn-primary">Imprimer / PDF</button>
        <a href="evenement.php?id=<?php echo $ticket['evenement_id']; ?>" class="btn btn-secondary">Voir l'événement</a>
    </div>
</body>
</html>

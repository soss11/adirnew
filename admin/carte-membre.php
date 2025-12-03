<?php
/**
 * Mini CRM - Génération de carte de membre PDF
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$currentUser = getCurrentUser();
$id = (int)($_GET['id'] ?? $currentUser['id']);

// Vérifier les droits
if (!isGestionnaire() && $id !== $currentUser['id']) {
    header('Location: ../dashboard.php');
    exit;
}

// Récupérer le membre
$stmt = $pdo->prepare("SELECT u.*,
    (SELECT MAX(c.date_fin) FROM cotisations c WHERE c.user_id = u.id AND c.statut = 'paye') as cotisation_valide_jusqu
    FROM users u WHERE u.id = ?");
$stmt->execute([$id]);
$membre = $stmt->fetch();

if (!$membre) {
    die('Membre non trouvé');
}

$asso = getAssociationSettings();

// Générer un numéro de membre si pas encore fait
if (empty($membre['numero_membre'])) {
    $numero = 'M' . date('Y') . sprintf('%04d', $membre['id']);
    $pdo->prepare("UPDATE users SET numero_membre = ? WHERE id = ?")->execute([$numero, $id]);
    $membre['numero_membre'] = $numero;
}

// Vérifier si cotisation à jour
$cotisationAJour = $membre['cotisation_valide_jusqu'] && strtotime($membre['cotisation_valide_jusqu']) >= time();

// QR Code avec le numéro de membre
$qrData = json_encode([
    'type' => 'membre',
    'id' => $membre['id'],
    'numero' => $membre['numero_membre'],
    'nom' => $membre['nom'] . ' ' . $membre['prenom']
]);
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrData);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Membre - <?php echo e($membre['prenom'] . ' ' . $membre['nom']); ?></title>
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

        .card-container {
            perspective: 1000px;
            margin-bottom: 20px;
        }

        .member-card {
            width: 400px;
            height: 250px;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 60%, #1a237e 100%);
            color: #fff;
        }

        .card-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.08) 0%, transparent 40%);
            pointer-events: none;
        }

        .card-content {
            position: relative;
            z-index: 1;
            height: 100%;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .card-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-logo img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
        }
        .card-logo-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }
        .card-org {
            font-size: 14px;
            font-weight: 600;
            opacity: 0.9;
        }

        .card-type {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-body {
            flex: 1;
            display: flex;
            gap: 16px;
        }

        .card-photo {
            width: 80px;
            height: 100px;
            border-radius: 8px;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .card-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .card-photo-placeholder {
            font-size: 36px;
            opacity: 0.5;
        }

        .card-info {
            flex: 1;
        }
        .card-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .card-number {
            font-family: monospace;
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }
        .card-detail {
            font-size: 12px;
            opacity: 0.8;
            margin-bottom: 4px;
        }

        .card-qr {
            position: absolute;
            bottom: 16px;
            right: 16px;
            width: 70px;
            height: 70px;
            background: #fff;
            border-radius: 8px;
            padding: 4px;
        }
        .card-qr img {
            width: 100%;
            height: 100%;
        }

        .card-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 8px 20px;
            background: rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }

        .card-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .status-dot.valid { background: #4ade80; }
        .status-dot.invalid { background: #f87171; }

        /* Back of card */
        .card-back {
            width: 400px;
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-top: 20px;
            color: #333;
        }
        .card-back h4 {
            font-size: 14px;
            margin-bottom: 12px;
            color: #1565c0;
        }
        .card-back p {
            font-size: 12px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
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
            .actions { display: none; }
            .card-back { box-shadow: none; border: 1px solid #ddd; }
            .member-card { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="card-container">
        <div class="member-card">
            <div class="card-pattern"></div>
            <div class="card-content">
                <div class="card-header">
                    <div class="card-logo">
                        <?php if (!empty($asso['logo'])): ?>
                            <img src="../assets/uploads/<?php echo e($asso['logo']); ?>" alt="Logo">
                        <?php else: ?>
                            <div class="card-logo-placeholder"><?php echo e(substr($asso['nom'] ?? 'A', 0, 2)); ?></div>
                        <?php endif; ?>
                        <span class="card-org"><?php echo e($asso['nom']); ?></span>
                    </div>
                    <span class="card-type">Carte Membre</span>
                </div>

                <div class="card-body">
                    <div class="card-photo">
                        <?php if (!empty($membre['photo'])): ?>
                            <img src="../assets/uploads/<?php echo e($membre['photo']); ?>" alt="Photo">
                        <?php else: ?>
                            <span class="card-photo-placeholder">👤</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-info">
                        <div class="card-name"><?php echo e($membre['prenom'] . ' ' . $membre['nom']); ?></div>
                        <div class="card-number"><?php echo e($membre['numero_membre']); ?></div>
                        <?php if ($membre['email']): ?>
                            <div class="card-detail"><?php echo e($membre['email']); ?></div>
                        <?php endif; ?>
                        <?php if ($membre['telephone']): ?>
                            <div class="card-detail"><?php echo e($membre['telephone']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-qr">
                    <img src="<?php echo $qrUrl; ?>" alt="QR Code">
                </div>

                <div class="card-footer">
                    <span>Membre depuis <?php echo date('Y', strtotime($membre['created_at'])); ?></span>
                    <span class="card-status">
                        <span class="status-dot <?php echo $cotisationAJour ? 'valid' : 'invalid'; ?>"></span>
                        <?php if ($cotisationAJour): ?>
                            Valide jusqu'au <?php echo formatDate($membre['cotisation_valide_jusqu']); ?>
                        <?php else: ?>
                            Cotisation non à jour
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card-back">
        <h4><?php echo e($asso['nom']); ?></h4>
        <p><?php echo e($asso['adresse']); ?></p>
        <p>📧 <?php echo e($asso['email']); ?> <?php if ($asso['tel']): ?>| 📞 <?php echo e($asso['tel']); ?><?php endif; ?></p>
        <hr style="margin: 12px 0; border: none; border-top: 1px solid #eee;">
        <p style="font-size: 10px; color: #999;">
            Cette carte est personnelle et non cessible. Elle doit être présentée sur demande.
            En cas de perte, contacter l'association.
        </p>
    </div>

    <div class="actions">
        <button onclick="window.print()" class="btn btn-primary">Imprimer / PDF</button>
        <a href="<?php echo isGestionnaire() ? 'membres.php' : '../profile.php'; ?>" class="btn btn-secondary">Retour</a>
    </div>
</body>
</html>

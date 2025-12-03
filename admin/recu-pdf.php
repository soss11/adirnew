<?php
/**
 * Mini CRM - Génération de reçu/attestation PDF
 * Utilise une approche HTML-to-PDF simple sans librairie externe
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('gestionnaire');

$type = $_GET['type'] ?? 'cotisation'; // cotisation ou attestation
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die('ID manquant');
}

// Récupérer les infos de l'association
$asso = getAssociationSettings();

if ($type === 'cotisation') {
    // Récupérer la cotisation
    $stmt = $pdo->prepare("SELECT c.*, ct.nom as type_nom, ct.description as type_description,
                          u.nom, u.prenom, u.email, u.telephone, u.adresse, u.ville, u.code_postal
                          FROM cotisations c
                          JOIN cotisation_types ct ON c.cotisation_type_id = ct.id
                          JOIN users u ON c.user_id = u.id
                          WHERE c.id = ?");
    $stmt->execute([$id]);
    $cotisation = $stmt->fetch();

    if (!$cotisation) {
        die('Cotisation non trouvée');
    }

    $titre = 'Reçu de cotisation';
    $numero = 'COT-' . str_pad($cotisation['id'], 6, '0', STR_PAD_LEFT);
    $montant = $cotisation['montant'];
    $date_paiement = $cotisation['date_paiement'] ?: $cotisation['created_at'];

} elseif ($type === 'attestation') {
    // Attestation d'adhésion
    $stmt = $pdo->prepare("SELECT u.*,
                          (SELECT c.date_fin FROM cotisations c WHERE c.user_id = u.id AND c.statut = 'paye' ORDER BY c.date_fin DESC LIMIT 1) as cotisation_fin
                          FROM users u WHERE u.id = ?");
    $stmt->execute([$id]);
    $membre = $stmt->fetch();

    if (!$membre) {
        die('Membre non trouvé');
    }

    $titre = 'Attestation d\'adhésion';
    $numero = 'ATT-' . str_pad($membre['id'], 6, '0', STR_PAD_LEFT) . '-' . date('Y');
}

// Mode de paiement en français
$modesPaiement = [
    'especes' => 'Espèces',
    'cheque' => 'Chèque',
    'carte' => 'Carte bancaire',
    'virement' => 'Virement bancaire',
    'autre' => 'Autre'
];

// Générer le PDF en HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo $titre; ?> - <?php echo $numero; ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            @page { margin: 2cm; }
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }

        .logo-section {
            flex: 1;
        }

        .logo-section h1 {
            margin: 0;
            color: #667eea;
            font-size: 24px;
        }

        .logo-section p {
            margin: 5px 0 0;
            color: #666;
            font-size: 13px;
        }

        .document-info {
            text-align: right;
        }

        .document-info h2 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .document-info .numero {
            color: #667eea;
            font-weight: bold;
            font-size: 16px;
        }

        .document-info .date {
            color: #666;
            font-size: 13px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 12px;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .info-box p {
            margin: 5px 0;
        }

        .info-box strong {
            color: #333;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .details-table th,
        .details-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .details-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }

        .total-row {
            background: #667eea;
            color: #fff;
        }

        .total-row td {
            border: none;
            font-weight: bold;
            font-size: 18px;
        }

        .attestation-text {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            font-size: 15px;
            line-height: 1.8;
        }

        .attestation-text .member-name {
            font-weight: bold;
            color: #667eea;
        }

        .footer {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }

        .signature {
            text-align: center;
        }

        .signature .line {
            width: 200px;
            height: 1px;
            background: #333;
            margin: 50px auto 10px;
        }

        .signature p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }

        .print-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .print-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-print {
            background: #667eea;
            color: #fff;
        }

        .btn-back {
            background: #f0f0f0;
            color: #333;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(102, 126, 234, 0.05);
            font-weight: bold;
            pointer-events: none;
            z-index: -1;
        }
    </style>
</head>
<body>

<div class="print-buttons no-print">
    <button class="btn-print" onclick="window.print()">Imprimer / Sauvegarder en PDF</button>
    <button class="btn-back" onclick="history.back()">Retour</button>
</div>

<?php if ($type === 'cotisation' && $cotisation['statut'] !== 'paye'): ?>
<div class="watermark">NON PAYÉ</div>
<?php endif; ?>

<div class="header">
    <div class="logo-section">
        <h1><?php echo e($asso['nom'] ?: 'Association'); ?></h1>
        <?php if (!empty($asso['adresse'])): ?>
            <p><?php echo nl2br(e($asso['adresse'])); ?></p>
        <?php endif; ?>
        <?php if (!empty($asso['email'])): ?>
            <p><?php echo e($asso['email']); ?></p>
        <?php endif; ?>
        <?php if (!empty($asso['telephone'])): ?>
            <p>Tél : <?php echo e($asso['telephone']); ?></p>
        <?php endif; ?>
    </div>
    <div class="document-info">
        <h2><?php echo $titre; ?></h2>
        <p class="numero"><?php echo $numero; ?></p>
        <p class="date">Date d'émission : <?php echo date('d/m/Y'); ?></p>
    </div>
</div>

<?php if ($type === 'cotisation'): ?>

<div class="section">
    <div class="section-title">Membre</div>
    <div class="info-box">
        <p><strong><?php echo e($cotisation['prenom'] . ' ' . $cotisation['nom']); ?></strong></p>
        <?php if ($cotisation['adresse']): ?>
            <p><?php echo e($cotisation['adresse']); ?></p>
            <?php if ($cotisation['code_postal'] || $cotisation['ville']): ?>
                <p><?php echo e($cotisation['code_postal'] . ' ' . $cotisation['ville']); ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($cotisation['email']): ?>
            <p><?php echo e($cotisation['email']); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="section">
    <div class="section-title">Détail de la cotisation</div>
    <table class="details-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Période</th>
                <th>Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong><?php echo e($cotisation['type_nom']); ?></strong>
                    <?php if ($cotisation['type_description']): ?>
                        <br><small style="color:#666;"><?php echo e($cotisation['type_description']); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    Du <?php echo date('d/m/Y', strtotime($cotisation['date_debut'])); ?><br>
                    Au <?php echo date('d/m/Y', strtotime($cotisation['date_fin'])); ?>
                </td>
                <td><?php echo number_format($cotisation['montant'], 2, ',', ' '); ?> €</td>
            </tr>
            <tr class="total-row">
                <td colspan="2">Total réglé</td>
                <td><?php echo number_format($cotisation['montant'], 2, ',', ' '); ?> €</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Informations de paiement</div>
    <div class="info-box">
        <p><strong>Date de paiement :</strong> <?php echo date('d/m/Y', strtotime($date_paiement)); ?></p>
        <p><strong>Mode de paiement :</strong> <?php echo $modesPaiement[$cotisation['mode_paiement']] ?? $cotisation['mode_paiement']; ?></p>
        <p><strong>Statut :</strong>
            <?php if ($cotisation['statut'] === 'paye'): ?>
                <span style="color:green;font-weight:bold;">PAYÉ</span>
            <?php else: ?>
                <span style="color:orange;font-weight:bold;">EN ATTENTE</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php else: // Attestation ?>

<div class="section">
    <div class="attestation-text">
        <p>Je soussigné(e), représentant légal de l'association <strong><?php echo e($asso['nom'] ?: 'notre association'); ?></strong>,
        atteste que :</p>

        <p><span class="member-name"><?php echo e($membre['prenom'] . ' ' . $membre['nom']); ?></span></p>

        <?php if ($membre['adresse']): ?>
            <p>Demeurant : <?php echo e($membre['adresse']); ?>
            <?php if ($membre['code_postal'] || $membre['ville']): ?>
                , <?php echo e($membre['code_postal'] . ' ' . $membre['ville']); ?>
            <?php endif; ?>
            </p>
        <?php endif; ?>

        <p>Est membre de notre association depuis le <strong><?php echo date('d/m/Y', strtotime($membre['created_at'])); ?></strong>.</p>

        <?php if ($membre['cotisation_fin'] && strtotime($membre['cotisation_fin']) >= time()): ?>
            <p>Sa cotisation est à jour et valide jusqu'au <strong><?php echo date('d/m/Y', strtotime($membre['cotisation_fin'])); ?></strong>.</p>
        <?php endif; ?>

        <p style="margin-top:20px;">Cette attestation est délivrée pour servir et valoir ce que de droit.</p>
    </div>
</div>

<?php endif; ?>

<div class="footer">
    <div class="signature">
        <div class="line"></div>
        <p>Le Président / La Présidente</p>
        <p style="margin-top:5px;color:#888;"><?php echo e($asso['nom'] ?: ''); ?></p>
    </div>
    <div class="signature">
        <div class="line"></div>
        <p>Cachet de l'association</p>
    </div>
</div>

<p style="text-align:center;color:#888;font-size:11px;margin-top:50px;">
    Document généré le <?php echo date('d/m/Y à H:i'); ?> - Réf: <?php echo $numero; ?>
</p>

</body>
</html>

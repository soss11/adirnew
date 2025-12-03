<?php
/**
 * Mini CRM - Formulaire public de feedback événement
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$eventId = (int)($_GET['event_id'] ?? 0);
$success = false;
$error = '';

// Récupérer l'événement
$event = null;
if ($eventId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
}

if (!$event) {
    $error = 'Événement non trouvé.';
}

// Traiter le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $event) {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $note_globale = (int)($_POST['note_globale'] ?? 0);
    $note_organisation = (int)($_POST['note_organisation'] ?? 0);
    $note_lieu = (int)($_POST['note_lieu'] ?? 0);
    $note_animation = (int)($_POST['note_animation'] ?? 0);
    $points_positifs = trim($_POST['points_positifs'] ?? '');
    $points_ameliorer = trim($_POST['points_ameliorer'] ?? '');
    $suggestions = trim($_POST['suggestions'] ?? '');
    $recommanderait = isset($_POST['recommanderait']) ? 1 : 0;

    // Vérifier si déjà répondu avec cet email
    if ($email) {
        $stmt = $pdo->prepare("SELECT id FROM event_feedback WHERE evenement_id = ? AND email_participant = ?");
        $stmt->execute([$eventId, $email]);
        if ($stmt->fetch()) {
            $error = 'Vous avez déjà donné votre avis pour cet événement.';
        }
    }

    if (!$error && $note_globale >= 1 && $note_globale <= 5) {
        // Chercher l'utilisateur par email
        $userId = null;
        if ($email) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) $userId = $user['id'];
        }

        $stmt = $pdo->prepare("INSERT INTO event_feedback
            (evenement_id, user_id, nom_participant, email_participant, note_globale, note_organisation,
             note_lieu, note_animation, points_positifs, points_ameliorer, suggestions, recommanderait, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$eventId, $userId, $nom, $email, $note_globale, $note_organisation,
                        $note_lieu, $note_animation, $points_positifs, $points_ameliorer, $suggestions, $recommanderait]);
        $success = true;
    } elseif (!$error) {
        $error = 'Veuillez donner une note globale.';
    }
}

$asso = getAssociationSettings();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre avis - <?php echo e($event['titre'] ?? 'Événement'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1a56db 0%, #7c3aed 100%);
            color: #fff;
            padding: 40px;
            text-align: center;
        }
        .header h1 { font-size: 24px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .content { padding: 40px; }
        .form-group { margin-bottom: 25px; }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #1a56db;
        }
        .form-group textarea { resize: vertical; min-height: 100px; }

        .star-rating {
            display: flex;
            gap: 8px;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .star-rating input { display: none; }
        .star-rating label {
            font-size: 32px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffc107;
        }

        .rating-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .rating-item {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .rating-item label {
            display: block;
            font-weight: 500;
            margin-bottom: 10px;
            color: #333;
        }
        .rating-item .star-rating {
            justify-content: center;
        }
        .rating-item .star-rating label {
            font-size: 24px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: #f0f7ff;
            border-radius: 12px;
            cursor: pointer;
        }
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #1a56db 0%, #7c3aed 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(26, 86, 219, 0.4);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

        .success-message {
            text-align: center;
            padding: 60px 40px;
        }
        .success-message .icon { font-size: 80px; margin-bottom: 20px; }
        .success-message h2 { color: #16a34a; margin-bottom: 10px; }

        @media (max-width: 600px) {
            .rating-row { grid-template-columns: 1fr; }
            .content { padding: 25px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="success-message">
                <div class="icon">&#10004;</div>
                <h2>Merci pour votre avis !</h2>
                <p style="color: #666;">Votre feedback nous aide à améliorer nos événements.</p>
            </div>
        <?php elseif ($event): ?>
            <div class="header">
                <h1>Donnez votre avis</h1>
                <p><?php echo e($event['titre']); ?> - <?php echo date('d/m/Y', strtotime($event['date_debut'])); ?></p>
            </div>

            <div class="content">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Votre nom (optionnel)</label>
                        <input type="text" name="nom" value="<?php echo e($_POST['nom'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Votre email (optionnel)</label>
                        <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Note globale *</label>
                        <div class="star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="note_globale" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" <?php echo ($_POST['note_globale'] ?? 0) == $i ? 'checked' : ''; ?>>
                                <label for="star<?php echo $i; ?>">&#9733;</label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="rating-row">
                        <div class="rating-item">
                            <label>Organisation</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="note_organisation" value="<?php echo $i; ?>" id="org<?php echo $i; ?>">
                                    <label for="org<?php echo $i; ?>">&#9733;</label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="rating-item">
                            <label>Lieu</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="note_lieu" value="<?php echo $i; ?>" id="lieu<?php echo $i; ?>">
                                    <label for="lieu<?php echo $i; ?>">&#9733;</label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="rating-item">
                            <label>Animation</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="note_animation" value="<?php echo $i; ?>" id="anim<?php echo $i; ?>">
                                    <label for="anim<?php echo $i; ?>">&#9733;</label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="rating-item">
                            <label class="checkbox-group" style="padding:0;background:none;">
                                <input type="checkbox" name="recommanderait" value="1">
                                <span>Je recommande</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Qu'avez-vous apprécié ?</label>
                        <textarea name="points_positifs" placeholder="Ce qui vous a plu..."><?php echo e($_POST['points_positifs'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Que pourrions-nous améliorer ?</label>
                        <textarea name="points_ameliorer" placeholder="Vos suggestions d'amélioration..."><?php echo e($_POST['points_ameliorer'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Autres suggestions</label>
                        <textarea name="suggestions" placeholder="Idées pour les prochains événements..."><?php echo e($_POST['suggestions'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn">Envoyer mon avis</button>
                </form>
            </div>
        <?php else: ?>
            <div class="success-message">
                <div class="icon">&#128533;</div>
                <h2>Événement non trouvé</h2>
                <p style="color: #666;">Ce lien n'est plus valide.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

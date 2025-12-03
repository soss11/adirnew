<?php
/**
 * Mini CRM - Inscription en ligne aux événements
 * Page publique (accessible sans connexion)
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$success = false;
$error = '';
$ticket_code = '';

if (!$id) {
    header('Location: index.php');
    exit;
}

// Récupérer l'événement
$stmt = $pdo->prepare("SELECT e.*,
    (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut IN ('confirme', 'en_attente') AND i.liste_attente = 0) as nb_inscrits,
    (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.liste_attente = 1) as nb_liste_attente
    FROM evenements e
    WHERE e.id = ? AND e.statut IN ('publie', 'complet')");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: index.php');
    exit;
}

$asso = getAssociationSettings();
$placesRestantes = $event['places_max'] > 0 ? max(0, $event['places_max'] - $event['nb_inscrits']) : 999;
$estComplet = $event['places_max'] > 0 && $placesRestantes <= 0;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $nombre_places = min((int)($_POST['nombre_places'] ?? 1), 5); // Max 5 places
    $est_membre = isset($_POST['est_membre']) && $_POST['est_membre'] === '1';
    $accepte_liste_attente = isset($_POST['accepte_liste_attente']);

    if (empty($nom) || empty($email)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        // Vérifier si déjà inscrit
        $stmt = $pdo->prepare("SELECT id FROM inscriptions WHERE evenement_id = ? AND email_participant = ? AND statut != 'annule'");
        $stmt->execute([$id, $email]);
        if ($stmt->fetch()) {
            $error = 'Vous êtes déjà inscrit(e) à cet événement.';
        } else {
            // Vérifier les places disponibles
            $enListeAttente = false;
            if ($event['places_max'] > 0) {
                $placesDisponibles = $placesRestantes;
                if ($placesDisponibles < $nombre_places) {
                    if ($accepte_liste_attente || $estComplet) {
                        $enListeAttente = true;
                    } else {
                        $error = "Il ne reste que $placesDisponibles place(s) disponible(s).";
                    }
                }
            }

            if (empty($error)) {
                // Vérifier si c'est un membre existant
                $user_id = null;
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND actif = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    $user_id = $user['id'];
                    $est_membre = true;
                }

                // Calculer le montant
                $prix_unitaire = $est_membre ? $event['prix_membre'] : $event['prix_non_membre'];
                $montant = $prix_unitaire * $nombre_places;

                // Générer le code ticket
                $ticket_code = strtoupper(substr(md5(uniqid()), 0, 2)) . sprintf('%04d', $id) . '-' . date('ym') . '-' . sprintf('%04d', rand(0, 9999));

                // Créer l'inscription
                $stmt = $pdo->prepare("INSERT INTO inscriptions
                    (evenement_id, user_id, nom_participant, prenom_participant, email_participant, telephone_participant, nombre_places, montant, statut, liste_attente, ticket_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $statut = $montant > 0 ? 'en_attente' : 'confirme';

                $stmt->execute([
                    $id,
                    $user_id,
                    $nom,
                    $prenom,
                    $email,
                    $telephone,
                    $nombre_places,
                    $montant,
                    $enListeAttente ? 'liste_attente' : $statut,
                    $enListeAttente ? 1 : 0,
                    $ticket_code
                ]);

                $success = true;

                // Mettre à jour le statut de l'événement si complet
                if (!$enListeAttente && $event['places_max'] > 0) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut IN ('confirme', 'en_attente') AND liste_attente = 0");
                    $stmt->execute([$id]);
                    if ($stmt->fetchColumn() >= $event['places_max']) {
                        $pdo->prepare("UPDATE evenements SET statut = 'complet' WHERE id = ?")->execute([$id]);
                    }
                }
            }
        }
    }
}

// Types d'événements
$eventTypes = [
    'spectacle' => ['label' => 'Spectacle', 'color' => '#e91e63', 'icon' => '🎭'],
    'soiree' => ['label' => 'Soirée', 'color' => '#9c27b0', 'icon' => '🎉'],
    'tombola' => ['label' => 'Tombola', 'color' => '#ff9800', 'icon' => '🎟️'],
    'atelier' => ['label' => 'Atelier', 'color' => '#4caf50', 'icon' => '🎨'],
    'reunion' => ['label' => 'Réunion', 'color' => '#2196f3', 'icon' => '👥'],
    'autre' => ['label' => 'Autre', 'color' => '#607d8b', 'icon' => '📅']
];
$type = $eventTypes[$event['type']] ?? $eventTypes['autre'];

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
    <title>Inscription - <?php echo e($event['titre']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 600px; margin: 0 auto; }
        .card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .card-header {
            background: <?php echo $type['color']; ?>;
            color: #fff;
            padding: 24px;
            text-align: center;
        }
        .card-header h1 { font-size: 24px; margin-bottom: 8px; }
        .card-header p { opacity: 0.9; }
        .card-body { padding: 24px; }

        .event-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .event-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
        }
        .event-info-item:not(:last-child) { border-bottom: 1px solid #e2e8f0; }
        .event-info-icon { font-size: 20px; width: 28px; text-align: center; }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #1a1a2e;
            font-size: 14px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #1565c0;
            box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.1);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .checkbox-group input { width: auto; }

        .price-info {
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
            color: #fff;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }
        .price-total {
            border-top: 1px solid rgba(255,255,255,0.3);
            margin-top: 8px;
            padding-top: 8px;
            font-size: 18px;
            font-weight: 600;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #1565c0;
            color: #fff;
        }
        .btn-primary:hover { background: #0d47a1; }
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            margin-top: 8px;
        }

        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-warning { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }

        .success-box {
            text-align: center;
            padding: 40px 20px;
        }
        .success-icon { font-size: 64px; margin-bottom: 16px; }
        .success-box h2 { margin-bottom: 8px; color: #16a34a; }
        .ticket-code {
            background: #f0fdf4;
            border: 2px dashed #16a34a;
            padding: 16px;
            border-radius: 12px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 24px;
            font-weight: 700;
            color: #16a34a;
        }

        .places-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .places-badge.available { background: #dcfce7; color: #16a34a; }
        .places-badge.limited { background: #fef3c7; color: #d97706; }
        .places-badge.full { background: #fee2e2; color: #dc2626; }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <p><?php echo $type['icon']; ?> <?php echo $type['label']; ?></p>
                <h1><?php echo e($event['titre']); ?></h1>
                <p><?php echo e($asso['nom']); ?></p>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="success-box">
                        <div class="success-icon">✅</div>
                        <h2>Inscription confirmée !</h2>
                        <p>Votre inscription a bien été enregistrée.</p>
                        <div class="ticket-code"><?php echo e($ticket_code); ?></div>
                        <p style="color: #64748b; font-size: 14px;">
                            Conservez ce code, il vous sera demandé à l'entrée.<br>
                            Un email de confirmation vous a été envoyé.
                        </p>
                        <a href="billet.php?code=<?php echo urlencode($ticket_code); ?>" class="btn btn-primary" style="margin-top: 20px;">
                            Télécharger mon billet PDF
                        </a>
                        <a href="evenement.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                            Retour à l'événement
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Infos événement -->
                    <div class="event-info">
                        <div class="event-info-item">
                            <span class="event-info-icon">📅</span>
                            <span><?php echo $dateFormatee; ?></span>
                        </div>
                        <?php if ($event['lieu']): ?>
                        <div class="event-info-item">
                            <span class="event-info-icon">📍</span>
                            <span><?php echo e($event['lieu']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="event-info-item">
                            <span class="event-info-icon">👥</span>
                            <span>
                                <?php if ($event['places_max'] > 0): ?>
                                    <?php if ($placesRestantes > 10): ?>
                                        <span class="places-badge available"><?php echo $placesRestantes; ?> places disponibles</span>
                                    <?php elseif ($placesRestantes > 0): ?>
                                        <span class="places-badge limited">Plus que <?php echo $placesRestantes; ?> place(s) !</span>
                                    <?php else: ?>
                                        <span class="places-badge full">Complet - Liste d'attente</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="places-badge available">Places illimitées</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo e($error); ?></div>
                    <?php endif; ?>

                    <?php if ($estComplet): ?>
                        <div class="alert alert-warning">
                            <strong>Événement complet !</strong><br>
                            Vous pouvez vous inscrire sur la liste d'attente. Vous serez contacté(e) si une place se libère.
                            <br><small>(<?php echo $event['nb_liste_attente']; ?> personne(s) en attente)</small>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom *</label>
                                <input type="text" name="nom" value="<?php echo e($_POST['nom'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Prénom</label>
                                <input type="text" name="prenom" value="<?php echo e($_POST['prenom'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Téléphone</label>
                                <input type="tel" name="telephone" value="<?php echo e($_POST['telephone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Nombre de places</label>
                                <select name="nombre_places" id="nombre_places" onchange="updatePrice()">
                                    <?php for ($i = 1; $i <= min(5, $placesRestantes > 0 ? $placesRestantes : 5); $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <?php if ($event['prix_membre'] != $event['prix_non_membre']): ?>
                        <div class="checkbox-group">
                            <input type="checkbox" name="est_membre" id="est_membre" value="1" onchange="updatePrice()">
                            <label for="est_membre" style="margin: 0; cursor: pointer;">Je suis membre de l'association</label>
                        </div>
                        <?php endif; ?>

                        <?php if ($estComplet): ?>
                        <div class="checkbox-group" style="background: #fef3c7;">
                            <input type="checkbox" name="accepte_liste_attente" id="accepte_liste_attente" checked>
                            <label for="accepte_liste_attente" style="margin: 0; cursor: pointer;">J'accepte d'être inscrit(e) sur la liste d'attente</label>
                        </div>
                        <?php endif; ?>

                        <?php if ($event['prix_membre'] > 0 || $event['prix_non_membre'] > 0): ?>
                        <div class="price-info">
                            <div class="price-row">
                                <span>Prix unitaire</span>
                                <span id="prix-unitaire"><?php echo number_format($event['prix_non_membre'], 2, ',', ' '); ?> €</span>
                            </div>
                            <div class="price-row">
                                <span>Nombre de places</span>
                                <span id="nb-places">1</span>
                            </div>
                            <div class="price-row price-total">
                                <span>Total à payer</span>
                                <span id="prix-total"><?php echo number_format($event['prix_non_membre'], 2, ',', ' '); ?> €</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary">
                            <?php echo $estComplet ? 'S\'inscrire sur la liste d\'attente' : 'Confirmer mon inscription'; ?>
                        </button>
                        <a href="evenement.php?id=<?php echo $id; ?>" class="btn btn-secondary">Retour</a>
                    </form>

                    <script>
                    const prixMembre = <?php echo $event['prix_membre']; ?>;
                    const prixNonMembre = <?php echo $event['prix_non_membre']; ?>;

                    function updatePrice() {
                        const estMembre = document.getElementById('est_membre')?.checked || false;
                        const nbPlaces = parseInt(document.getElementById('nombre_places').value) || 1;
                        const prixUnitaire = estMembre ? prixMembre : prixNonMembre;
                        const total = prixUnitaire * nbPlaces;

                        document.getElementById('prix-unitaire').textContent = prixUnitaire.toFixed(2).replace('.', ',') + ' €';
                        document.getElementById('nb-places').textContent = nbPlaces;
                        document.getElementById('prix-total').textContent = total.toFixed(2).replace('.', ',') + ' €';
                    }
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

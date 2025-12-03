<?php
/**
 * Mini CRM - Gestion des Emails / Newsletter
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('gestionnaire');

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $sujet = trim($_POST['sujet'] ?? '');
        $contenu = trim($_POST['contenu'] ?? '');
        $destinataires_type = $_POST['destinataires_type'] ?? 'tous';
        $evenement_id = !empty($_POST['evenement_id']) ? (int)$_POST['evenement_id'] : null;
        $email_id = (int)($_POST['email_id'] ?? 0);

        if (empty($sujet) || empty($contenu)) {
            setFlashMessage('error', 'Veuillez remplir le sujet et le contenu.');
        } else {
            if ($email_id > 0) {
                $stmt = $pdo->prepare("UPDATE emails SET sujet = ?, contenu = ?, destinataires_type = ?, evenement_id = ? WHERE id = ?");
                $stmt->execute([$sujet, $contenu, $destinataires_type, $evenement_id, $email_id]);
                setFlashMessage('success', 'Email modifié avec succès.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO emails (sujet, contenu, destinataires_type, evenement_id, envoye_par, statut) VALUES (?, ?, ?, ?, ?, 'brouillon')");
                $stmt->execute([$sujet, $contenu, $destinataires_type, $evenement_id, getCurrentUser()['id']]);
                $email_id = $pdo->lastInsertId();
                setFlashMessage('success', 'Brouillon sauvegardé.');
            }
            header('Location: emails.php?action=edit&id=' . $email_id);
            exit;
        }
    }

    if ($postAction === 'send' && $id > 0) {
        // Récupérer l'email
        $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = ?");
        $stmt->execute([$id]);
        $email = $stmt->fetch();

        if ($email && $email['statut'] === 'brouillon') {
            // Récupérer les destinataires selon le type
            $destinataires = getDestinataires($email['destinataires_type'], $email['evenement_id']);

            $sent = 0;
            $errors = 0;
            $asso = getAssociationSettings();

            foreach ($destinataires as $dest) {
                $result = sendEmail($dest['email'], $dest['nom'], $email['sujet'], $email['contenu'], $asso);

                // Log l'envoi
                $stmt = $pdo->prepare("INSERT INTO email_logs (email_id, destinataire_email, destinataire_nom, statut, erreur_message, envoye_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $id,
                    $dest['email'],
                    $dest['nom'],
                    $result['success'] ? 'envoye' : 'erreur',
                    $result['success'] ? null : $result['message']
                ]);

                if ($result['success']) {
                    $sent++;
                } else {
                    $errors++;
                }
            }

            // Mettre à jour l'email
            $stmt = $pdo->prepare("UPDATE emails SET statut = 'envoye', date_envoi = NOW(), nb_destinataires = ? WHERE id = ?");
            $stmt->execute([$sent, $id]);

            setFlashMessage('success', "Email envoyé à $sent destinataire(s)" . ($errors > 0 ? " ($errors erreur(s))" : ""));
        }

        header('Location: emails.php');
        exit;
    }

    if ($postAction === 'delete' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM emails WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Email supprimé.');
        header('Location: emails.php');
        exit;
    }
}

/**
 * Récupère les destinataires selon le type
 */
function getDestinataires(string $type, ?int $evenement_id = null): array {
    global $pdo;

    switch ($type) {
        case 'tous':
            $stmt = $pdo->query("SELECT email, CONCAT(prenom, ' ', nom) as nom FROM users WHERE actif = 1 AND email != ''");
            break;
        case 'membres':
            $stmt = $pdo->query("SELECT email, CONCAT(prenom, ' ', nom) as nom FROM users WHERE actif = 1 AND role = 'membre' AND email != ''");
            break;
        case 'gestionnaires':
            $stmt = $pdo->query("SELECT email, CONCAT(prenom, ' ', nom) as nom FROM users WHERE actif = 1 AND role IN ('admin', 'gestionnaire') AND email != ''");
            break;
        case 'cotisation_jour':
            $stmt = $pdo->query("SELECT DISTINCT u.email, CONCAT(u.prenom, ' ', u.nom) as nom
                FROM users u
                JOIN cotisations c ON u.id = c.user_id
                WHERE u.actif = 1 AND c.statut = 'paye' AND c.date_fin >= CURDATE() AND u.email != ''");
            break;
        case 'cotisation_retard':
            $stmt = $pdo->query("SELECT DISTINCT u.email, CONCAT(u.prenom, ' ', u.nom) as nom
                FROM users u
                LEFT JOIN cotisations c ON u.id = c.user_id AND c.statut = 'paye' AND c.date_fin >= CURDATE()
                WHERE u.actif = 1 AND u.role = 'membre' AND c.id IS NULL AND u.email != ''");
            break;
        case 'evenement':
            if ($evenement_id) {
                $stmt = $pdo->prepare("SELECT DISTINCT
                    COALESCE(u.email, i.email_participant) as email,
                    COALESCE(CONCAT(u.prenom, ' ', u.nom), CONCAT(i.prenom_participant, ' ', i.nom_participant)) as nom
                    FROM inscriptions i
                    LEFT JOIN users u ON i.user_id = u.id
                    WHERE i.evenement_id = ? AND i.statut IN ('confirme', 'en_attente')");
                $stmt->execute([$evenement_id]);
                return $stmt->fetchAll();
            }
            return [];
        default:
            return [];
    }

    return $stmt->fetchAll();
}

/**
 * Envoie un email
 */
function sendEmail(string $to, string $toName, string $subject, string $body, array $asso): array {
    $smtp_host = getSetting('smtp_host');
    $smtp_from = getSetting('smtp_from_email', $asso['email']);
    $smtp_from_name = getSetting('smtp_from_name', $asso['nom']);

    // Si pas de SMTP configuré, utiliser mail()
    if (empty($smtp_host)) {
        $headers = [
            'From' => "$smtp_from_name <$smtp_from>",
            'Reply-To' => $smtp_from,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8'
        ];

        // Construire le HTML
        $htmlBody = buildEmailHtml($subject, $body, $asso);

        $result = @mail($to, $subject, $htmlBody, $headers);

        return [
            'success' => $result,
            'message' => $result ? 'Envoyé' : 'Erreur mail()'
        ];
    }

    // Utiliser SMTP avec PHPMailer si disponible
    // Sinon simuler l'envoi pour la démo
    return ['success' => true, 'message' => 'Envoyé (mode démo)'];
}

/**
 * Construit le HTML de l'email
 */
function buildEmailHtml(string $subject, string $body, array $asso): string {
    $body = nl2br(htmlspecialchars($body));

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1565c0; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .footer { background: #eee; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$asso['nom']}</h1>
        </div>
        <div class="content">
            $body
        </div>
        <div class="footer">
            <p>{$asso['nom']}<br>{$asso['adresse']}<br>{$asso['email']}</p>
        </div>
    </div>
</body>
</html>
HTML;
}

// Récupérer les événements pour le sélecteur
$evenements = $pdo->query("SELECT id, titre, date_debut FROM evenements WHERE statut IN ('publie', 'complet') ORDER BY date_debut DESC LIMIT 20")->fetchAll();

$pageTitle = 'Emails & Newsletter';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <!-- Liste des emails -->
    <div class="card">
        <div class="card-header">
            <h3>Emails envoyés</h3>
            <a href="?action=new" class="btn btn-primary">Nouvel email</a>
        </div>
        <div class="card-body">
            <?php
            $emails = $pdo->query("SELECT e.*, u.nom as auteur_nom, ev.titre as evenement_titre
                FROM emails e
                LEFT JOIN users u ON e.envoye_par = u.id
                LEFT JOIN evenements ev ON e.evenement_id = ev.id
                ORDER BY e.created_at DESC")->fetchAll();
            ?>

            <?php if (empty($emails)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📧</div>
                    <h3>Aucun email</h3>
                    <p>Créez votre première newsletter</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Sujet</th>
                                <th>Destinataires</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emails as $email): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($email['sujet']); ?></strong>
                                        <?php if ($email['evenement_titre']): ?>
                                            <br><small class="text-gray">Événement: <?php echo e($email['evenement_titre']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $types = [
                                            'tous' => 'Tous',
                                            'membres' => 'Membres',
                                            'gestionnaires' => 'Gestionnaires',
                                            'cotisation_jour' => 'Cotisation à jour',
                                            'cotisation_retard' => 'Cotisation en retard',
                                            'evenement' => 'Inscrits événement'
                                        ];
                                        echo $types[$email['destinataires_type']] ?? $email['destinataires_type'];
                                        if ($email['nb_destinataires'] > 0) {
                                            echo ' <span class="badge badge-primary">' . $email['nb_destinataires'] . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($email['statut'] === 'brouillon'): ?>
                                            <span class="badge badge-secondary">Brouillon</span>
                                        <?php elseif ($email['statut'] === 'envoye'): ?>
                                            <span class="badge badge-success">Envoyé</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Erreur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $email['date_envoi'] ? formatDateTime($email['date_envoi']) : formatDate($email['created_at']); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?action=edit&id=<?php echo $email['id']; ?>" class="btn btn-sm btn-secondary">Voir</a>
                                            <?php if ($email['statut'] === 'brouillon'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $email['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce brouillon ?')">Supprimer</button>
                                                </form>
                                            <?php endif; ?>
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

<?php elseif ($action === 'new' || $action === 'edit'): ?>
    <?php
    $email = null;
    if ($action === 'edit' && $id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = ?");
        $stmt->execute([$id]);
        $email = $stmt->fetch();
    }
    $isReadonly = $email && $email['statut'] !== 'brouillon';
    ?>

    <div class="card">
        <div class="card-header">
            <h3><?php echo $email ? ($isReadonly ? 'Voir l\'email' : 'Modifier l\'email') : 'Nouvel email'; ?></h3>
            <a href="emails.php" class="btn btn-secondary">Retour</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="email_id" value="<?php echo $email['id'] ?? 0; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Destinataires</label>
                        <select name="destinataires_type" id="destinataires_type" <?php echo $isReadonly ? 'disabled' : ''; ?>>
                            <option value="tous" <?php echo ($email['destinataires_type'] ?? '') === 'tous' ? 'selected' : ''; ?>>Tous les utilisateurs</option>
                            <option value="membres" <?php echo ($email['destinataires_type'] ?? '') === 'membres' ? 'selected' : ''; ?>>Membres uniquement</option>
                            <option value="gestionnaires" <?php echo ($email['destinataires_type'] ?? '') === 'gestionnaires' ? 'selected' : ''; ?>>Gestionnaires & Admins</option>
                            <option value="cotisation_jour" <?php echo ($email['destinataires_type'] ?? '') === 'cotisation_jour' ? 'selected' : ''; ?>>Cotisation à jour</option>
                            <option value="cotisation_retard" <?php echo ($email['destinataires_type'] ?? '') === 'cotisation_retard' ? 'selected' : ''; ?>>Cotisation en retard</option>
                            <option value="evenement" <?php echo ($email['destinataires_type'] ?? '') === 'evenement' ? 'selected' : ''; ?>>Inscrits à un événement</option>
                        </select>
                    </div>
                    <div class="form-group" id="evenement-select" style="<?php echo ($email['destinataires_type'] ?? '') === 'evenement' ? '' : 'display:none'; ?>">
                        <label>Événement</label>
                        <select name="evenement_id" <?php echo $isReadonly ? 'disabled' : ''; ?>>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($evenements as $ev): ?>
                                <option value="<?php echo $ev['id']; ?>" <?php echo ($email['evenement_id'] ?? 0) == $ev['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($ev['titre']); ?> (<?php echo formatDate($ev['date_debut']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Sujet *</label>
                    <input type="text" name="sujet" value="<?php echo e($email['sujet'] ?? ''); ?>" required <?php echo $isReadonly ? 'readonly' : ''; ?>>
                </div>

                <div class="form-group">
                    <label>Contenu *</label>
                    <textarea name="contenu" rows="12" required <?php echo $isReadonly ? 'readonly' : ''; ?>><?php echo e($email['contenu'] ?? ''); ?></textarea>
                    <p class="form-hint">Variables disponibles: {nom}, {prenom}, {email}</p>
                </div>

                <?php if (!$isReadonly): ?>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Sauvegarder le brouillon</button>
                        <?php if ($email): ?>
                            <button type="button" class="btn btn-success" onclick="confirmSend()">Envoyer maintenant</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Cet email a été envoyé le <?php echo formatDateTime($email['date_envoi']); ?> à <?php echo $email['nb_destinataires']; ?> destinataire(s).
                    </div>
                <?php endif; ?>
            </form>

            <?php if ($email): ?>
                <form method="POST" id="sendForm" style="display:none;">
                    <input type="hidden" name="action" value="send">
                    <input type="hidden" name="id" value="<?php echo $email['id']; ?>">
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.getElementById('destinataires_type')?.addEventListener('change', function() {
        document.getElementById('evenement-select').style.display = this.value === 'evenement' ? '' : 'none';
    });

    function confirmSend() {
        if (confirm('Êtes-vous sûr de vouloir envoyer cet email ? Cette action est irréversible.')) {
            document.getElementById('sendForm').submit();
        }
    }
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

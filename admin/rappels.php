<?php
/**
 * Mini CRM - Configuration des rappels automatiques
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('admin');

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? '';

// Enregistrer la configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $configs = $_POST['config'] ?? [];

    foreach ($configs as $type => $data) {
        $stmt = $pdo->prepare("UPDATE rappels_config SET actif = ?, delai_jours = ?, sujet = ?, template = ? WHERE type_rappel = ?");
        $stmt->execute([
            isset($data['actif']) ? 1 : 0,
            (int)($data['delai_jours'] ?? 7),
            $data['sujet'] ?? '',
            $data['template'] ?? '',
            $type
        ]);
    }

    setFlashMessage('success', 'Configuration enregistrée.');
    header('Location: rappels.php');
    exit;
}

// Envoyer manuellement
if ($action === 'send' && isset($_GET['type'])) {
    $type = $_GET['type'];
    $count = sendReminders($pdo, $type);
    setFlashMessage('success', $count . ' rappel(s) envoyé(s).');
    header('Location: rappels.php');
    exit;
}

/**
 * Fonction d'envoi des rappels
 */
function sendReminders($pdo, $type = null) {
    $asso = getAssociationSettings();
    $count = 0;

    // Récupérer les configurations actives
    $sql = "SELECT * FROM rappels_config WHERE actif = 1";
    if ($type) {
        $sql .= " AND type_rappel = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type]);
    } else {
        $stmt = $pdo->query($sql);
    }
    $configs = $stmt->fetchAll();

    foreach ($configs as $config) {
        $users = [];

        switch ($config['type_rappel']) {
            case 'cotisation_expiration':
                // Cotisations qui expirent dans X jours
                $stmt = $pdo->prepare("
                    SELECT DISTINCT u.*, c.date_fin
                    FROM users u
                    JOIN cotisations c ON u.id = c.user_id
                    WHERE c.statut = 'paye'
                    AND c.date_fin = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    AND u.actif = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM rappels_logs r
                        WHERE r.user_id = u.id
                        AND r.type_rappel = 'cotisation_expiration'
                        AND r.reference_id = c.id
                    )
                ");
                $stmt->execute([$config['delai_jours']]);
                $users = $stmt->fetchAll();
                break;

            case 'cotisation_retard':
                // Cotisations expirées depuis X jours
                $stmt = $pdo->prepare("
                    SELECT DISTINCT u.*, c.date_fin
                    FROM users u
                    JOIN cotisations c ON u.id = c.user_id
                    WHERE c.statut = 'paye'
                    AND c.date_fin = DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    AND u.actif = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM rappels_logs r
                        WHERE r.user_id = u.id
                        AND r.type_rappel = 'cotisation_retard'
                        AND DATE(r.envoye_at) = CURDATE()
                    )
                ");
                $stmt->execute([$config['delai_jours']]);
                $users = $stmt->fetchAll();
                break;

            case 'evenement':
                // Événements dans X jours
                $stmt = $pdo->prepare("
                    SELECT DISTINCT u.*, e.titre as event_titre, e.date_debut as event_date, e.lieu as event_lieu, e.id as event_id
                    FROM users u
                    JOIN inscriptions i ON u.id = i.user_id
                    JOIN evenements e ON i.evenement_id = e.id
                    WHERE DATE(e.date_debut) = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    AND i.statut IN ('confirme', 'en_attente')
                    AND u.actif = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM rappels_logs r
                        WHERE r.user_id = u.id
                        AND r.type_rappel = 'evenement'
                        AND r.reference_id = e.id
                    )
                ");
                $stmt->execute([$config['delai_jours']]);
                $users = $stmt->fetchAll();
                break;

            case 'anniversaire':
                // Anniversaires aujourd'hui
                $stmt = $pdo->query("
                    SELECT u.*
                    FROM users u
                    WHERE DATE_FORMAT(u.date_naissance, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
                    AND u.actif = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM rappels_logs r
                        WHERE r.user_id = u.id
                        AND r.type_rappel = 'anniversaire'
                        AND YEAR(r.envoye_at) = YEAR(CURDATE())
                    )
                ");
                $users = $stmt->fetchAll();
                break;
        }

        foreach ($users as $user) {
            // Préparer le message
            $sujet = $config['sujet'];
            $message = $config['template'];

            // Remplacements
            $replacements = [
                '{prenom}' => $user['prenom'],
                '{nom}' => $user['nom'],
                '{email}' => $user['email'],
                '{numero_membre}' => $user['numero_membre'] ?? '',
                '{asso_nom}' => $asso['nom'],
                '{date_fin}' => isset($user['date_fin']) ? date('d/m/Y', strtotime($user['date_fin'])) : '',
                '{event_titre}' => $user['event_titre'] ?? '',
                '{event_date}' => isset($user['event_date']) ? date('d/m/Y à H:i', strtotime($user['event_date'])) : '',
                '{event_lieu}' => $user['event_lieu'] ?? ''
            ];

            foreach ($replacements as $key => $value) {
                $sujet = str_replace($key, $value, $sujet);
                $message = str_replace($key, $value, $message);
            }

            // Envoyer l'email
            $headers = "From: " . ($asso['email'] ?: 'noreply@example.com') . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            $sent = @mail($user['email'], $sujet, $message, $headers);

            // Logger
            $stmt = $pdo->prepare("INSERT INTO rappels_logs (type_rappel, user_id, reference_type, reference_id, statut, envoye_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $refId = $user['event_id'] ?? ($user['cotisation_id'] ?? null);
            $stmt->execute([
                $config['type_rappel'],
                $user['id'],
                $config['type_rappel'],
                $refId,
                $sent ? 'envoye' : 'erreur'
            ]);

            if ($sent) $count++;
        }
    }

    return $count;
}

$pageTitle = 'Rappels automatiques';
require_once __DIR__ . '/../includes/header.php';

// Récupérer les configurations
$configs = $pdo->query("SELECT * FROM rappels_config ORDER BY id")->fetchAll();

// Stats des rappels envoyés
$stats = $pdo->query("
    SELECT type_rappel, COUNT(*) as total, MAX(envoye_at) as dernier
    FROM rappels_logs
    WHERE statut = 'envoye'
    GROUP BY type_rappel
")->fetchAll(PDO::FETCH_UNIQUE);

$typeLabels = [
    'cotisation_expiration' => ['label' => 'Expiration cotisation', 'icon' => '&#128197;', 'desc' => 'Rappel avant expiration de la cotisation'],
    'cotisation_retard' => ['label' => 'Cotisation en retard', 'icon' => '&#9888;', 'desc' => 'Rappel après expiration de la cotisation'],
    'evenement' => ['label' => 'Rappel événement', 'icon' => '&#127881;', 'desc' => 'Rappel avant un événement'],
    'anniversaire' => ['label' => 'Anniversaire', 'icon' => '&#127874;', 'desc' => 'Message d\'anniversaire'],
    'bienvenue' => ['label' => 'Bienvenue', 'icon' => '&#128075;', 'desc' => 'Message aux nouveaux membres']
];

$variables = [
    '{prenom}' => 'Prénom du membre',
    '{nom}' => 'Nom du membre',
    '{email}' => 'Email du membre',
    '{numero_membre}' => 'Numéro de membre',
    '{asso_nom}' => 'Nom de l\'association',
    '{date_fin}' => 'Date fin cotisation',
    '{event_titre}' => 'Titre de l\'événement',
    '{event_date}' => 'Date de l\'événement',
    '{event_lieu}' => 'Lieu de l\'événement'
];
?>

<style>
.reminder-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
}
.reminder-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}
.reminder-header .icon {
    font-size: 24px;
    margin-right: 15px;
}
.reminder-header .toggle {
    position: relative;
    width: 50px;
    height: 26px;
}
.reminder-header .toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}
.reminder-header .toggle .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: #cbd5e1;
    border-radius: 26px;
    transition: 0.3s;
}
.reminder-header .toggle .slider:before {
    content: "";
    position: absolute;
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: #fff;
    border-radius: 50%;
    transition: 0.3s;
}
.reminder-header .toggle input:checked + .slider {
    background: #10b981;
}
.reminder-header .toggle input:checked + .slider:before {
    transform: translateX(24px);
}
.reminder-body {
    padding: 20px;
}
.reminder-stats {
    display: flex;
    gap: 20px;
    font-size: 13px;
    color: #666;
    margin-top: 10px;
}
.var-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}
.var-tag {
    background: #e0e7ff;
    color: #4338ca;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-family: monospace;
    cursor: pointer;
}
.var-tag:hover {
    background: #c7d2fe;
}
</style>

<div class="card">
    <div class="card-header">
        <h3>Configuration des rappels automatiques</h3>
    </div>
    <div class="card-body">
        <div style="background: #f0f7ff; border: 1px solid #bfdbfe; padding: 15px; border-radius: 10px; margin-bottom: 30px;">
            <strong>&#128161; Conseil :</strong> Configurez un CRON job pour exécuter <code>/cron/rappels.php</code> quotidiennement pour l'envoi automatique.
        </div>

        <form method="POST">
            <input type="hidden" name="save_config" value="1">

            <?php foreach ($configs as $config): ?>
                <?php $info = $typeLabels[$config['type_rappel']] ?? ['label' => $config['type_rappel'], 'icon' => '&#128231;', 'desc' => '']; ?>
                <div class="reminder-card">
                    <div class="reminder-header">
                        <div style="display: flex; align-items: center;">
                            <span class="icon"><?php echo $info['icon']; ?></span>
                            <div>
                                <strong><?php echo $info['label']; ?></strong>
                                <div style="font-size: 13px; color: #666;"><?php echo $info['desc']; ?></div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <?php if (isset($stats[$config['type_rappel']])): ?>
                                <div class="reminder-stats">
                                    <span><?php echo $stats[$config['type_rappel']]['total']; ?> envoyés</span>
                                    <span>Dernier: <?php echo date('d/m/Y', strtotime($stats[$config['type_rappel']]['dernier'])); ?></span>
                                </div>
                            <?php endif; ?>
                            <a href="?action=send&type=<?php echo $config['type_rappel']; ?>"
                               onclick="return confirm('Envoyer maintenant les rappels en attente ?')"
                               class="btn btn-sm btn-secondary">Envoyer maintenant</a>
                            <label class="toggle">
                                <input type="checkbox" name="config[<?php echo $config['type_rappel']; ?>][actif]" <?php echo $config['actif'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="reminder-body">
                        <div class="form-row">
                            <?php if ($config['type_rappel'] !== 'anniversaire' && $config['type_rappel'] !== 'bienvenue'): ?>
                                <div class="form-group" style="max-width: 150px;">
                                    <label>Délai (jours)</label>
                                    <input type="number" name="config[<?php echo $config['type_rappel']; ?>][delai_jours]"
                                           value="<?php echo $config['delai_jours']; ?>" min="0">
                                    <p class="form-hint">
                                        <?php if ($config['type_rappel'] === 'cotisation_expiration'): ?>
                                            Avant expiration
                                        <?php elseif ($config['type_rappel'] === 'cotisation_retard'): ?>
                                            Après expiration
                                        <?php else: ?>
                                            Avant l'événement
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <div class="form-group" style="flex: 1;">
                                <label>Sujet de l'email</label>
                                <input type="text" name="config[<?php echo $config['type_rappel']; ?>][sujet]"
                                       value="<?php echo e($config['sujet']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Contenu du message</label>
                            <textarea name="config[<?php echo $config['type_rappel']; ?>][template]"
                                      rows="4"><?php echo e($config['template']); ?></textarea>
                            <div class="var-list">
                                <?php foreach ($variables as $var => $desc): ?>
                                    <span class="var-tag" title="<?php echo $desc; ?>" onclick="insertVar(this, '<?php echo $var; ?>')"><?php echo $var; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">Enregistrer la configuration</button>
        </form>
    </div>
</div>

<!-- Historique -->
<div class="card">
    <div class="card-header">
        <h3>Historique des envois</h3>
    </div>
    <div class="card-body">
        <?php
        $logs = $pdo->query("
            SELECT l.*, u.nom, u.prenom, u.email
            FROM rappels_logs l
            JOIN users u ON l.user_id = u.id
            ORDER BY l.envoye_at DESC
            LIMIT 50
        ")->fetchAll();
        ?>

        <?php if (empty($logs)): ?>
            <p style="color: #666; text-align: center;">Aucun rappel envoyé pour le moment.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Destinataire</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['envoye_at'])); ?></td>
                                <td><?php echo $typeLabels[$log['type_rappel']]['label'] ?? $log['type_rappel']; ?></td>
                                <td><?php echo e($log['prenom'] . ' ' . $log['nom']); ?> <span style="color:#666;">(<?php echo e($log['email']); ?>)</span></td>
                                <td>
                                    <?php if ($log['statut'] === 'envoye'): ?>
                                        <span class="badge badge-success">Envoyé</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Erreur</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function insertVar(el, varName) {
    var textarea = el.closest('.form-group').querySelector('textarea');
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var text = textarea.value;
    textarea.value = text.substring(0, start) + varName + text.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + varName.length;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

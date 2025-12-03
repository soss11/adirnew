<?php
/**
 * Mini CRM - Gestion des clés API
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('admin');

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? '';
$keyId = (int)($_GET['id'] ?? 0);

// Supprimer une clé
if ($action === 'delete' && $keyId > 0) {
    $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ?");
    $stmt->execute([$keyId]);
    setFlashMessage('success', 'Clé API supprimée.');
    header('Location: api.php');
    exit;
}

// Désactiver/activer une clé
if ($action === 'toggle' && $keyId > 0) {
    $stmt = $pdo->prepare("UPDATE api_keys SET actif = NOT actif WHERE id = ?");
    $stmt->execute([$keyId]);
    setFlashMessage('success', 'Statut de la clé modifié.');
    header('Location: api.php');
    exit;
}

// Créer une nouvelle clé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_key'])) {
    $nom = trim($_POST['nom'] ?? '');
    $permissions = $_POST['permissions'] ?? [];
    $expireAt = $_POST['expire_at'] ?: null;

    if (empty($nom)) {
        setFlashMessage('error', 'Le nom est obligatoire.');
    } else {
        $apiKey = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare("INSERT INTO api_keys (user_id, nom, api_key, permissions, expire_at, actif, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([
            $currentUser['id'],
            $nom,
            $apiKey,
            json_encode($permissions),
            $expireAt
        ]);

        setFlashMessage('success', 'Clé API créée. Copiez-la maintenant, elle ne sera plus visible: ' . $apiKey);
    }
    header('Location: api.php');
    exit;
}

$pageTitle = 'API REST';
require_once __DIR__ . '/../includes/header.php';

// Récupérer les clés
$keys = $pdo->query("
    SELECT k.*, u.nom as user_nom, u.prenom as user_prenom
    FROM api_keys k
    JOIN users u ON k.user_id = u.id
    ORDER BY k.created_at DESC
")->fetchAll();

// Stats d'utilisation
$stats = $pdo->query("
    SELECT api_key_id, COUNT(*) as nb_requetes, MAX(created_at) as derniere
    FROM api_logs
    GROUP BY api_key_id
")->fetchAll(PDO::FETCH_UNIQUE);

$allPermissions = [
    'members.read' => 'Lire les membres',
    'members.write' => 'Modifier les membres',
    'events.read' => 'Lire les événements',
    'events.write' => 'Modifier les événements',
    'subscriptions.read' => 'Lire les cotisations',
    'subscriptions.write' => 'Modifier les cotisations',
    'stats.read' => 'Lire les statistiques'
];
?>

<style>
.api-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
}
.api-card.inactive {
    opacity: 0.6;
    background: #f9fafb;
}
.api-key-display {
    font-family: monospace;
    background: #1f2937;
    color: #10b981;
    padding: 15px;
    border-radius: 8px;
    word-break: break-all;
}
.endpoint-card {
    background: #f9fafb;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
}
.endpoint-card .method {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    margin-right: 10px;
}
.endpoint-card .method.get { background: #dbeafe; color: #1d4ed8; }
.endpoint-card .method.post { background: #dcfce7; color: #166534; }
.endpoint-card .method.put { background: #fef3c7; color: #92400e; }
.endpoint-card .method.delete { background: #fee2e2; color: #991b1b; }
.perm-badge {
    display: inline-block;
    padding: 3px 10px;
    background: #e0e7ff;
    color: #4338ca;
    border-radius: 15px;
    font-size: 11px;
    margin: 2px;
}
</style>

<div class="card">
    <div class="card-header">
        <h3>Clés API</h3>
        <button onclick="openModal('createKeyModal')" class="btn btn-sm btn-primary">+ Nouvelle clé</button>
    </div>
    <div class="card-body">
        <?php if (empty($keys)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128273;</div>
                <h3>Aucune clé API</h3>
                <p>Créez une clé pour utiliser l'API REST.</p>
                <button onclick="openModal('createKeyModal')" class="btn btn-primary">Créer une clé</button>
            </div>
        <?php else: ?>
            <?php foreach ($keys as $key): ?>
                <div class="api-card <?php echo $key['actif'] ? '' : 'inactive'; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h4 style="margin: 0 0 5px;"><?php echo e($key['nom']); ?></h4>
                            <div style="font-size: 13px; color: #666;">
                                Créée par <?php echo e($key['user_prenom'] . ' ' . $key['user_nom']); ?>
                                le <?php echo date('d/m/Y', strtotime($key['created_at'])); ?>
                            </div>
                            <div style="margin-top: 10px;">
                                <?php
                                $perms = json_decode($key['permissions'], true) ?: [];
                                foreach ($perms as $p):
                                ?>
                                    <span class="perm-badge"><?php echo $allPermissions[$p] ?? $p; ?></span>
                                <?php endforeach; ?>
                                <?php if (empty($perms)): ?>
                                    <span class="perm-badge" style="background: #fee2e2; color: #991b1b;">Aucune permission</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <?php if ($key['actif']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                            <?php if ($key['expire_at']): ?>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                    Expire: <?php echo date('d/m/Y', strtotime($key['expire_at'])); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($stats[$key['id']])): ?>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                    <?php echo $stats[$key['id']]['nb_requetes']; ?> requêtes
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-top: 15px;">
                        <code style="background: #f3f4f6; padding: 8px 12px; border-radius: 6px; font-size: 12px;">
                            <?php echo substr($key['api_key'], 0, 8); ?>...<?php echo substr($key['api_key'], -8); ?>
                        </code>
                        <div class="btn-group" style="margin-left: 15px;">
                            <a href="?action=toggle&id=<?php echo $key['id']; ?>" class="btn btn-sm btn-secondary">
                                <?php echo $key['actif'] ? 'Désactiver' : 'Activer'; ?>
                            </a>
                            <a href="?action=delete&id=<?php echo $key['id']; ?>" onclick="return confirm('Supprimer cette clé ?')" class="btn btn-sm" style="color: #dc2626;">Supprimer</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Documentation API -->
<div class="card">
    <div class="card-header">
        <h3>Documentation API</h3>
    </div>
    <div class="card-body">
        <h4>Authentification</h4>
        <p>Ajoutez votre clé API dans le header HTTP :</p>
        <div class="api-key-display">
            Authorization: Bearer VOTRE_CLE_API
        </div>

        <h4 style="margin-top: 30px;">Endpoints disponibles</h4>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <code>/api/v1/members</code>
            <p style="margin: 10px 0 0; color: #666;">Liste des membres (requiert: members.read)</p>
        </div>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <code>/api/v1/members/{id}</code>
            <p style="margin: 10px 0 0; color: #666;">Détails d'un membre (requiert: members.read)</p>
        </div>

        <div class="endpoint-card">
            <span class="method post">POST</span>
            <code>/api/v1/members</code>
            <p style="margin: 10px 0 0; color: #666;">Créer un membre (requiert: members.write)</p>
        </div>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <code>/api/v1/events</code>
            <p style="margin: 10px 0 0; color: #666;">Liste des événements (requiert: events.read)</p>
        </div>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <code>/api/v1/events/{id}</code>
            <p style="margin: 10px 0 0; color: #666;">Détails d'un événement avec inscriptions (requiert: events.read)</p>
        </div>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <code>/api/v1/subscriptions</code>
            <p style="margin: 10px 0 0; color: #666;">Liste des cotisations (requiert: subscriptions.read)</p>
        </div>

        <div class="endpoint-card">
            <span class="method get">GET</span>
            <code>/api/v1/stats</code>
            <p style="margin: 10px 0 0; color: #666;">Statistiques globales (requiert: stats.read)</p>
        </div>

        <h4 style="margin-top: 30px;">Exemple de réponse</h4>
        <pre style="background: #1f2937; color: #e5e7eb; padding: 20px; border-radius: 8px; overflow-x: auto;">
{
  "success": true,
  "data": [
    {
      "id": 1,
      "numero_membre": "M-0001",
      "nom": "Dupont",
      "prenom": "Jean",
      "email": "jean.dupont@email.com"
    }
  ],
  "total": 1,
  "page": 1,
  "per_page": 50
}</pre>
    </div>
</div>

<!-- Modal créer clé -->
<div class="modal-overlay" id="createKeyModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouvelle clé API</h3>
            <button class="modal-close" onclick="closeModal('createKeyModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="create_key" value="1">

                <div class="form-group">
                    <label>Nom de la clé *</label>
                    <input type="text" name="nom" placeholder="Ex: Application mobile" required>
                </div>

                <div class="form-group">
                    <label>Permissions</label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <?php foreach ($allPermissions as $key => $label): ?>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>">
                                <?php echo $label; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Date d'expiration (optionnel)</label>
                    <input type="date" name="expire_at">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createKeyModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

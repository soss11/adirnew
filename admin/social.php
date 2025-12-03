<?php
/**
 * Mini CRM - Gestion des publications réseaux sociaux
 */

require_once __DIR__ . '/../includes/auth.php';
requireGestionnaire();

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    // Configurer un compte
    if ($postAction === 'save_account') {
        $plateforme = $_POST['plateforme'];
        $data = [
            'nom_compte' => $_POST['nom_compte'],
            'page_id' => $_POST['page_id'] ?? '',
            'access_token' => $_POST['access_token'] ?? '',
            'actif' => isset($_POST['actif']) ? 1 : 0,
            'connected_by' => getCurrentUserId()
        ];

        // Vérifier si existe déjà
        $stmt = $pdo->prepare("SELECT id FROM social_accounts WHERE plateforme = ?");
        $stmt->execute([$plateforme]);
        $existing = $stmt->fetch();

        if ($existing) {
            $sets = [];
            foreach ($data as $key => $value) {
                $sets[] = "$key = ?";
            }
            $stmt = $pdo->prepare("UPDATE social_accounts SET " . implode(', ', $sets) . " WHERE plateforme = ?");
            $stmt->execute([...array_values($data), $plateforme]);
        } else {
            $data['plateforme'] = $plateforme;
            $stmt = $pdo->prepare("INSERT INTO social_accounts (plateforme, nom_compte, page_id, access_token, actif, connected_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$plateforme, $data['nom_compte'], $data['page_id'], $data['access_token'], $data['actif'], $data['connected_by']]);
        }

        setFlashMessage('success', 'Compte ' . ucfirst($plateforme) . ' configuré.');
        header('Location: social.php?action=accounts');
        exit;
    }

    // Créer/modifier une publication
    if ($postAction === 'create' || $postAction === 'update') {
        $plateformes = $_POST['plateformes'] ?? [];

        $data = [
            'evenement_id' => $_POST['evenement_id'] ?: null,
            'plateformes' => json_encode($plateformes),
            'type_post' => $_POST['type_post'],
            'contenu' => $_POST['contenu'],
            'lien' => $_POST['lien'] ?? '',
            'date_publication' => $_POST['date_publication'] ?: null,
            'publie_immediatement' => isset($_POST['publie_immediatement']) ? 1 : 0,
            'statut' => isset($_POST['publie_immediatement']) ? 'planifie' : ($_POST['statut'] ?? 'brouillon')
        ];

        // Upload image
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = __DIR__ . '/../assets/uploads/social/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $filename = 'post_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $data['image'] = $filename;
            }
        }

        if ($postAction === 'create') {
            $data['cree_par'] = getCurrentUserId();
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            $sql = "INSERT INTO social_posts (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            $postId = $pdo->lastInsertId();

            // Si publication immédiate
            if ($data['publie_immediatement']) {
                publishToSocialMedia($pdo, $postId);
            }

            setFlashMessage('success', 'Publication créée.');
            header('Location: social.php');
            exit;
        } else {
            $postId = intval($_POST['post_id']);
            $sets = [];
            foreach ($data as $key => $value) {
                $sets[] = "$key = ?";
            }
            $sql = "UPDATE social_posts SET " . implode(', ', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([...array_values($data), $postId]);

            if ($data['publie_immediatement']) {
                publishToSocialMedia($pdo, $postId);
            }

            setFlashMessage('success', 'Publication mise à jour.');
            header('Location: social.php');
            exit;
        }
    }

    // Publier maintenant
    if ($postAction === 'publish_now') {
        $postId = intval($_POST['post_id']);
        publishToSocialMedia($pdo, $postId);
        setFlashMessage('success', 'Publication envoyée aux réseaux sociaux.');
        header('Location: social.php');
        exit;
    }

    // Générer depuis événement
    if ($postAction === 'generate_from_event') {
        $eventId = intval($_POST['evenement_id']);
        $typePost = $_POST['type_post'];

        $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if ($event) {
            $asso = getAssociationSettings();
            $dateStr = date('d/m/Y à H:i', strtotime($event['date_debut']));

            $templates = [
                'annonce' => "Nouveau ! {$event['titre']}\n\nRDV le {$dateStr}\n{$event['lieu']}\n\n{$event['description']}\n\nInscriptions ouvertes !",
                'rappel' => "Rappel : {$event['titre']} c'est bientôt !\n\nRDV le {$dateStr} à {$event['lieu']}\n\nIl reste encore quelques places !",
                'compte_rendu' => "Retour sur {$event['titre']} !\n\nMerci à tous les participants pour ce moment convivial.\n\nRendez-vous au prochain événement !"
            ];

            $contenu = $templates[$typePost] ?? $templates['annonce'];

            // Créer le post
            $stmt = $pdo->prepare("INSERT INTO social_posts (evenement_id, plateformes, type_post, contenu, image, cree_par) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $eventId,
                json_encode(['facebook', 'instagram']),
                $typePost,
                $contenu,
                $event['affiche'],
                getCurrentUserId()
            ]);

            setFlashMessage('success', 'Publication générée. Vous pouvez la modifier avant de publier.');
            header('Location: social.php?action=edit&id=' . $pdo->lastInsertId());
            exit;
        }
    }

    // Supprimer
    if ($postAction === 'delete') {
        $postId = intval($_POST['post_id']);
        $stmt = $pdo->prepare("DELETE FROM social_posts WHERE id = ?");
        $stmt->execute([$postId]);
        setFlashMessage('success', 'Publication supprimée.');
        header('Location: social.php');
        exit;
    }
}

/**
 * Fonction pour publier sur les réseaux sociaux
 * Note: Cette fonction simule l'envoi. En production, utiliser les APIs Facebook/Instagram
 */
function publishToSocialMedia($pdo, $postId) {
    $stmt = $pdo->prepare("SELECT * FROM social_posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post) return false;

    $plateformes = json_decode($post['plateformes'], true) ?: [];

    foreach ($plateformes as $plateforme) {
        // Vérifier si compte configuré
        $stmt = $pdo->prepare("SELECT * FROM social_accounts WHERE plateforme = ? AND actif = 1");
        $stmt->execute([$plateforme]);
        $account = $stmt->fetch();

        if ($account) {
            // Simulation de publication
            // En production: appeler l'API Facebook/Instagram avec le access_token

            $success = true; // Simulé
            $externalId = 'post_' . uniqid(); // Simulé

            // Log
            $stmt = $pdo->prepare("INSERT INTO social_logs (post_id, plateforme, post_externe_id, statut, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $postId,
                $plateforme,
                $success ? $externalId : null,
                $success ? 'succes' : 'erreur',
                $success ? 'Publication simulée avec succès' : 'Erreur de publication'
            ]);
        }
    }

    // Mettre à jour le statut
    $stmt = $pdo->prepare("UPDATE social_posts SET statut = 'publie', date_publication = NOW() WHERE id = ?");
    $stmt->execute([$postId]);

    return true;
}

$pageTitle = 'Réseaux sociaux';
require_once __DIR__ . '/../includes/header.php';

$plateformesInfo = [
    'facebook' => ['name' => 'Facebook', 'icon' => 'f', 'color' => '#1877f2'],
    'instagram' => ['name' => 'Instagram', 'icon' => 'ig', 'color' => '#e4405f'],
    'twitter' => ['name' => 'Twitter/X', 'icon' => 'x', 'color' => '#000'],
    'linkedin' => ['name' => 'LinkedIn', 'icon' => 'in', 'color' => '#0077b5']
];

$typesPost = [
    'annonce' => 'Annonce événement',
    'rappel' => 'Rappel événement',
    'compte_rendu' => 'Compte-rendu',
    'photo' => 'Photo/Album',
    'custom' => 'Publication libre'
];
?>

<?php if ($action === 'list'): ?>
    <?php
    // Publications
    $stmt = $pdo->query("
        SELECT p.*, e.titre as event_titre, u.prenom, u.nom,
            (SELECT COUNT(*) FROM social_logs WHERE post_id = p.id AND statut = 'succes') as nb_publie
        FROM social_posts p
        LEFT JOIN evenements e ON p.evenement_id = e.id
        LEFT JOIN users u ON p.cree_par = u.id
        ORDER BY p.created_at DESC
    ");
    $posts = $stmt->fetchAll();

    // Comptes configurés
    $stmt = $pdo->query("SELECT * FROM social_accounts WHERE actif = 1");
    $accounts = $stmt->fetchAll();
    $activeAccounts = array_column($accounts, 'plateforme');
    ?>

    <div class="card mb-4">
        <div class="card-header">
            <h3>Publications réseaux sociaux</h3>
            <div>
                <a href="?action=accounts" class="btn btn-secondary">Configurer comptes</a>
                <a href="?action=new" class="btn btn-primary">+ Nouvelle publication</a>
            </div>
        </div>
        <div class="card-body">
            <!-- Comptes actifs -->
            <div class="mb-4">
                <strong>Comptes connectés :</strong>
                <?php if (empty($activeAccounts)): ?>
                    <span class="text-muted">Aucun compte configuré.</span>
                    <a href="?action=accounts">Configurer</a>
                <?php else: ?>
                    <?php foreach ($activeAccounts as $p): ?>
                        <span class="badge" style="background:<?php echo $plateformesInfo[$p]['color']; ?>;color:#fff;margin-right:5px;">
                            <?php echo $plateformesInfo[$p]['name']; ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Génération rapide -->
            <div class="card bg-light mb-4">
                <div class="card-body">
                    <h5>Générer depuis un événement</h5>
                    <form method="POST" class="form-inline">
                        <input type="hidden" name="action" value="generate_from_event">
                        <select name="evenement_id" class="form-control mr-2" required>
                            <option value="">-- Sélectionner un événement --</option>
                            <?php
                            $events = $pdo->query("SELECT id, titre, date_debut FROM evenements WHERE statut IN ('publie', 'termine') ORDER BY date_debut DESC LIMIT 20")->fetchAll();
                            foreach ($events as $e): ?>
                                <option value="<?php echo $e['id']; ?>">
                                    <?php echo e($e['titre']); ?> (<?php echo date('d/m/Y', strtotime($e['date_debut'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="type_post" class="form-control mr-2">
                            <option value="annonce">Annonce</option>
                            <option value="rappel">Rappel</option>
                            <option value="compte_rendu">Compte-rendu</option>
                        </select>
                        <button type="submit" class="btn btn-info">Générer</button>
                    </form>
                </div>
            </div>

            <!-- Liste des publications -->
            <?php if (empty($posts)): ?>
                <p class="text-muted">Aucune publication créée.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Publication</th>
                            <th>Plateformes</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post):
                            $postPlateformes = json_decode($post['plateformes'], true) ?: [];
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo e(mb_substr($post['contenu'], 0, 50)); ?>...</strong>
                                    <?php if ($post['event_titre']): ?>
                                        <br><small class="text-muted">Événement: <?php echo e($post['event_titre']); ?></small>
                                    <?php endif; ?>
                                    <br><small class="badge badge-secondary"><?php echo e($typesPost[$post['type_post']]); ?></small>
                                </td>
                                <td>
                                    <?php foreach ($postPlateformes as $p): ?>
                                        <span class="badge" style="background:<?php echo $plateformesInfo[$p]['color'] ?? '#666'; ?>;color:#fff;">
                                            <?php echo $plateformesInfo[$p]['name'] ?? $p; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php
                                    $statutColors = [
                                        'brouillon' => 'secondary',
                                        'planifie' => 'warning',
                                        'publie' => 'success',
                                        'erreur' => 'danger'
                                    ];
                                    $statutLabels = [
                                        'brouillon' => 'Brouillon',
                                        'planifie' => 'Planifié',
                                        'publie' => 'Publié',
                                        'erreur' => 'Erreur'
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $statutColors[$post['statut']]; ?>">
                                        <?php echo $statutLabels[$post['statut']]; ?>
                                    </span>
                                    <?php if ($post['nb_publie'] > 0): ?>
                                        <br><small class="text-success"><?php echo $post['nb_publie']; ?> plateforme(s)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($post['date_publication']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($post['date_publication'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non planifié</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?action=edit&id=<?php echo $post['id']; ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                    <?php if ($post['statut'] !== 'publie'): ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="publish_now">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Publier maintenant ?')">Publier</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'accounts'): ?>
    <?php
    // Récupérer les comptes existants
    $stmt = $pdo->query("SELECT * FROM social_accounts");
    $accountsData = [];
    while ($row = $stmt->fetch()) {
        $accountsData[$row['plateforme']] = $row;
    }
    ?>

    <div class="card">
        <div class="card-header">
            <h3>Configuration des comptes</h3>
            <a href="social.php" class="btn btn-secondary">Retour</a>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Note :</strong> Pour connecter Facebook/Instagram, vous devez créer une application sur
                <a href="https://developers.facebook.com" target="_blank">Facebook Developers</a> et obtenir un Access Token.
            </div>

            <div class="row">
                <?php foreach ($plateformesInfo as $key => $info):
                    $account = $accountsData[$key] ?? null;
                ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header" style="background:<?php echo $info['color']; ?>;color:#fff;">
                                <h5 class="mb-0"><?php echo $info['name']; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_account">
                                    <input type="hidden" name="plateforme" value="<?php echo $key; ?>">

                                    <div class="form-group">
                                        <label>Nom du compte/page</label>
                                        <input type="text" name="nom_compte" class="form-control"
                                               value="<?php echo e($account['nom_compte'] ?? ''); ?>"
                                               placeholder="Ex: Association XYZ">
                                    </div>

                                    <div class="form-group">
                                        <label>Page ID</label>
                                        <input type="text" name="page_id" class="form-control"
                                               value="<?php echo e($account['page_id'] ?? ''); ?>"
                                               placeholder="ID de la page Facebook/Instagram">
                                    </div>

                                    <div class="form-group">
                                        <label>Access Token</label>
                                        <input type="password" name="access_token" class="form-control"
                                               value="<?php echo e($account['access_token'] ?? ''); ?>"
                                               placeholder="Token d'accès API">
                                    </div>

                                    <div class="form-group">
                                        <label class="d-flex align-items-center">
                                            <input type="checkbox" name="actif" class="mr-2"
                                                   <?php echo ($account['actif'] ?? 0) ? 'checked' : ''; ?>>
                                            Compte actif
                                        </label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Enregistrer</button>

                                    <?php if ($account && $account['actif']): ?>
                                        <span class="badge badge-success ml-2">Connecté</span>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card bg-light mt-4">
                <div class="card-body">
                    <h5>Comment obtenir les tokens ?</h5>
                    <ol>
                        <li>Créez une application sur <a href="https://developers.facebook.com" target="_blank">Facebook Developers</a></li>
                        <li>Ajoutez les produits "Facebook Login" et "Instagram Graph API"</li>
                        <li>Générez un Page Access Token avec les permissions <code>pages_manage_posts</code>, <code>pages_read_engagement</code></li>
                        <li>Pour Instagram, liez votre compte Business Instagram à la page Facebook</li>
                        <li>Copiez le token ici (attention: il expire, utilisez un token longue durée)</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
    <?php
    $post = null;
    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM social_posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
    }

    $selectedPlateformes = $post ? (json_decode($post['plateformes'], true) ?: []) : ['facebook', 'instagram'];

    // Événements pour lier
    $events = $pdo->query("SELECT id, titre, date_debut FROM evenements ORDER BY date_debut DESC LIMIT 50")->fetchAll();
    ?>

    <div class="card">
        <div class="card-header">
            <h3><?php echo $post ? 'Modifier la publication' : 'Nouvelle publication'; ?></h3>
            <a href="social.php" class="btn btn-secondary">Retour</a>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $post ? 'update' : 'create'; ?>">
                <?php if ($post): ?>
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Plateformes *</label>
                        <div>
                            <?php foreach ($plateformesInfo as $key => $info): ?>
                                <label class="d-inline-block mr-3">
                                    <input type="checkbox" name="plateformes[]" value="<?php echo $key; ?>"
                                           <?php echo in_array($key, $selectedPlateformes) ? 'checked' : ''; ?>>
                                    <span class="badge" style="background:<?php echo $info['color']; ?>;color:#fff;">
                                        <?php echo $info['name']; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Type de publication</label>
                        <select name="type_post" class="form-control">
                            <?php foreach ($typesPost as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($post['type_post'] ?? 'custom') === $key ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Lier à un événement</label>
                    <select name="evenement_id" class="form-control">
                        <option value="">-- Aucun événement --</option>
                        <?php foreach ($events as $e): ?>
                            <option value="<?php echo $e['id']; ?>" <?php echo ($post['evenement_id'] ?? '') == $e['id'] ? 'selected' : ''; ?>>
                                <?php echo e($e['titre']); ?> (<?php echo date('d/m/Y', strtotime($e['date_debut'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Contenu de la publication *</label>
                    <textarea name="contenu" class="form-control" rows="8" required placeholder="Écrivez votre publication ici...&#10;&#10;Utilisez des emojis et des hashtags !"><?php echo e($post['contenu'] ?? ''); ?></textarea>
                    <small class="text-muted">
                        <span id="charCount">0</span> caractères
                        (Facebook: 63 206 max, Twitter: 280 max, Instagram: 2 200 max)
                    </small>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if (!empty($post['image'])): ?>
                            <small>Actuel: <?php echo e($post['image']); ?></small>
                            <br>
                            <img src="<?php echo getBaseUrl(); ?>/assets/uploads/social/<?php echo e($post['image']); ?>" style="max-width:200px;margin-top:10px;">
                        <?php endif; ?>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Lien (optionnel)</label>
                        <input type="url" name="lien" class="form-control" value="<?php echo e($post['lien'] ?? ''); ?>" placeholder="https://...">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Date de publication planifiée</label>
                        <input type="datetime-local" name="date_publication" class="form-control"
                               value="<?php echo $post['date_publication'] ? date('Y-m-d\TH:i', strtotime($post['date_publication'])) : ''; ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Statut</label>
                        <select name="statut" class="form-control">
                            <option value="brouillon" <?php echo ($post['statut'] ?? 'brouillon') === 'brouillon' ? 'selected' : ''; ?>>Brouillon</option>
                            <option value="planifie" <?php echo ($post['statut'] ?? '') === 'planifie' ? 'selected' : ''; ?>>Planifié</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="d-flex align-items-center">
                        <input type="checkbox" name="publie_immediatement" class="mr-2">
                        <strong>Publier immédiatement</strong>
                    </label>
                </div>

                <div class="form-actions">
                    <a href="social.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>

            <?php if ($post): ?>
                <hr>
                <form method="POST" onsubmit="return confirm('Supprimer cette publication ?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>

                <?php
                // Logs de publication
                $stmt = $pdo->prepare("SELECT * FROM social_logs WHERE post_id = ? ORDER BY created_at DESC");
                $stmt->execute([$post['id']]);
                $logs = $stmt->fetchAll();

                if (!empty($logs)):
                ?>
                    <hr>
                    <h5>Historique de publication</h5>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Plateforme</th>
                                <th>Statut</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <span class="badge" style="background:<?php echo $plateformesInfo[$log['plateforme']]['color'] ?? '#666'; ?>;color:#fff;">
                                            <?php echo $plateformesInfo[$log['plateforme']]['name'] ?? $log['plateforme']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $log['statut'] === 'succes' ? 'success' : 'danger'; ?>">
                                            <?php echo $log['statut'] === 'succes' ? 'Succès' : 'Erreur'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo e($log['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.querySelector('textarea[name="contenu"]').addEventListener('input', function() {
        document.getElementById('charCount').textContent = this.value.length;
    });
    // Init
    document.getElementById('charCount').textContent = document.querySelector('textarea[name="contenu"]').value.length;
    </script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

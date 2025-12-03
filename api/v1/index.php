<?php
/**
 * Mini CRM - API REST v1
 *
 * Endpoints:
 * GET /api/v1/members - Liste des membres
 * GET /api/v1/members/{id} - Détails d'un membre
 * POST /api/v1/members - Créer un membre
 * GET /api/v1/events - Liste des événements
 * GET /api/v1/events/{id} - Détails d'un événement
 * GET /api/v1/subscriptions - Liste des cotisations
 * GET /api/v1/stats - Statistiques
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$startTime = microtime(true);

// Fonction de réponse JSON
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function jsonError($message, $code = 400) {
    jsonResponse(['success' => false, 'error' => $message], $code);
}

// Authentification par clé API
function authenticate($pdo) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $apiKey = $matches[1];
    } else {
        $apiKey = $_GET['api_key'] ?? '';
    }

    if (empty($apiKey)) {
        jsonError('Clé API manquante', 401);
    }

    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE api_key = ? AND actif = 1 AND (expire_at IS NULL OR expire_at > NOW())");
    $stmt->execute([$apiKey]);
    $key = $stmt->fetch();

    if (!$key) {
        jsonError('Clé API invalide ou expirée', 401);
    }

    // Mettre à jour les stats
    $pdo->prepare("UPDATE api_keys SET derniere_utilisation = NOW(), nb_requetes = nb_requetes + 1 WHERE id = ?")->execute([$key['id']]);

    return $key;
}

// Vérifier les permissions
function checkPermission($key, $permission) {
    $permissions = json_decode($key['permissions'], true) ?: [];
    if (!in_array($permission, $permissions)) {
        jsonError('Permission refusée: ' . $permission, 403);
    }
}

// Logger la requête
function logRequest($pdo, $keyId, $endpoint, $method, $code, $startTime) {
    $executionTime = microtime(true) - $startTime;
    $stmt = $pdo->prepare("INSERT INTO api_logs (api_key_id, endpoint, method, ip_address, response_code, execution_time, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$keyId, $endpoint, $method, $_SERVER['REMOTE_ADDR'] ?? '', $code, $executionTime]);
}

// Parser l'URL
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/v1';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'];

// Authentification
$apiKey = authenticate($pdo);

// Routing
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;

try {
    switch ($resource) {
        case 'members':
            if ($method === 'GET' && !$id) {
                // Liste des membres
                checkPermission($apiKey, 'members.read');

                $page = max(1, (int)($_GET['page'] ?? 1));
                $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
                $offset = ($page - 1) * $perPage;

                $search = $_GET['search'] ?? '';
                $where = "WHERE role = 'membre' AND actif = 1";
                $params = [];

                if ($search) {
                    $where .= " AND (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
                    $params = ["%$search%", "%$search%", "%$search%"];
                }

                $total = $pdo->prepare("SELECT COUNT(*) FROM users $where");
                $total->execute($params);
                $totalCount = $total->fetchColumn();

                $stmt = $pdo->prepare("SELECT id, numero_membre, nom, prenom, email, telephone, ville, pays_origine, date_naissance, profession, created_at FROM users $where ORDER BY nom LIMIT $perPage OFFSET $offset");
                $stmt->execute($params);
                $members = $stmt->fetchAll();

                jsonResponse([
                    'success' => true,
                    'data' => $members,
                    'total' => (int)$totalCount,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($totalCount / $perPage)
                ]);

            } elseif ($method === 'GET' && $id) {
                // Détails d'un membre
                checkPermission($apiKey, 'members.read');

                $stmt = $pdo->prepare("SELECT id, numero_membre, nom, prenom, email, telephone, adresse, ville, code_postal, pays_origine, date_naissance, profession, created_at FROM users WHERE id = ? AND role = 'membre'");
                $stmt->execute([(int)$id]);
                $member = $stmt->fetch();

                if (!$member) {
                    jsonError('Membre non trouvé', 404);
                }

                // Cotisations
                $stmt = $pdo->prepare("SELECT c.*, ct.nom as type_nom FROM cotisations c JOIN cotisation_types ct ON c.cotisation_type_id = ct.id WHERE c.user_id = ? ORDER BY c.date_fin DESC");
                $stmt->execute([(int)$id]);
                $member['cotisations'] = $stmt->fetchAll();

                jsonResponse(['success' => true, 'data' => $member]);

            } elseif ($method === 'POST') {
                // Créer un membre
                checkPermission($apiKey, 'members.write');

                $input = json_decode(file_get_contents('php://input'), true);

                if (empty($input['email']) || empty($input['nom'])) {
                    jsonError('Email et nom sont obligatoires');
                }

                // Vérifier email unique
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$input['email']]);
                if ($stmt->fetch()) {
                    jsonError('Cet email est déjà utilisé');
                }

                // Générer numéro membre
                $lastNum = $pdo->query("SELECT MAX(CAST(SUBSTRING(numero_membre, 3) AS UNSIGNED)) FROM users WHERE numero_membre LIKE 'M-%'")->fetchColumn();
                $numeroMembre = 'M-' . str_pad(($lastNum ?: 0) + 1, 4, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("INSERT INTO users (numero_membre, email, password, nom, prenom, role, telephone, ville, pays_origine, date_naissance, profession, actif, created_at) VALUES (?, ?, ?, ?, ?, 'membre', ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([
                    $numeroMembre,
                    $input['email'],
                    password_hash($input['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
                    $input['nom'],
                    $input['prenom'] ?? null,
                    $input['telephone'] ?? null,
                    $input['ville'] ?? null,
                    $input['pays_origine'] ?? null,
                    $input['date_naissance'] ?? null,
                    $input['profession'] ?? null
                ]);

                $memberId = $pdo->lastInsertId();

                jsonResponse([
                    'success' => true,
                    'message' => 'Membre créé',
                    'data' => ['id' => (int)$memberId, 'numero_membre' => $numeroMembre]
                ], 201);

            } else {
                jsonError('Méthode non supportée', 405);
            }
            break;

        case 'events':
            if ($method === 'GET' && !$id) {
                // Liste des événements
                checkPermission($apiKey, 'events.read');

                $page = max(1, (int)($_GET['page'] ?? 1));
                $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
                $offset = ($page - 1) * $perPage;

                $status = $_GET['status'] ?? '';
                $where = "WHERE 1=1";
                $params = [];

                if ($status) {
                    $where .= " AND statut = ?";
                    $params[] = $status;
                }

                $total = $pdo->prepare("SELECT COUNT(*) FROM evenements $where");
                $total->execute($params);
                $totalCount = $total->fetchColumn();

                $stmt = $pdo->prepare("SELECT id, titre, description, type, date_debut, date_fin, lieu, places_max, prix_membre, prix_non_membre, statut, created_at FROM evenements $where ORDER BY date_debut DESC LIMIT $perPage OFFSET $offset");
                $stmt->execute($params);
                $events = $stmt->fetchAll();

                jsonResponse([
                    'success' => true,
                    'data' => $events,
                    'total' => (int)$totalCount,
                    'page' => $page,
                    'per_page' => $perPage
                ]);

            } elseif ($method === 'GET' && $id) {
                // Détails d'un événement
                checkPermission($apiKey, 'events.read');

                $stmt = $pdo->prepare("SELECT * FROM evenements WHERE id = ?");
                $stmt->execute([(int)$id]);
                $event = $stmt->fetch();

                if (!$event) {
                    jsonError('Événement non trouvé', 404);
                }

                // Inscriptions
                $stmt = $pdo->prepare("SELECT i.id, i.nom_participant, i.prenom_participant, i.email_participant, i.nombre_places, i.montant, i.statut, i.created_at FROM inscriptions i WHERE i.evenement_id = ?");
                $stmt->execute([(int)$id]);
                $event['inscriptions'] = $stmt->fetchAll();

                // Stats
                $event['stats'] = [
                    'total_inscrits' => count($event['inscriptions']),
                    'places_reservees' => array_sum(array_column($event['inscriptions'], 'nombre_places')),
                    'montant_total' => array_sum(array_column($event['inscriptions'], 'montant'))
                ];

                jsonResponse(['success' => true, 'data' => $event]);

            } else {
                jsonError('Méthode non supportée', 405);
            }
            break;

        case 'subscriptions':
            checkPermission($apiKey, 'subscriptions.read');

            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;

            $status = $_GET['status'] ?? '';
            $where = "WHERE 1=1";
            $params = [];

            if ($status) {
                $where .= " AND c.statut = ?";
                $params[] = $status;
            }

            $stmt = $pdo->prepare("SELECT c.*, ct.nom as type_nom, u.nom as membre_nom, u.prenom as membre_prenom, u.email as membre_email FROM cotisations c JOIN cotisation_types ct ON c.cotisation_type_id = ct.id JOIN users u ON c.user_id = u.id $where ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset");
            $stmt->execute($params);
            $subscriptions = $stmt->fetchAll();

            jsonResponse([
                'success' => true,
                'data' => $subscriptions,
                'page' => $page,
                'per_page' => $perPage
            ]);
            break;

        case 'stats':
            checkPermission($apiKey, 'stats.read');

            $stats = [];

            // Membres
            $stats['members'] = [
                'total' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'membre' AND actif = 1")->fetchColumn(),
                'new_this_month' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'membre' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn()
            ];

            // Événements
            $stats['events'] = [
                'total' => (int)$pdo->query("SELECT COUNT(*) FROM evenements")->fetchColumn(),
                'upcoming' => (int)$pdo->query("SELECT COUNT(*) FROM evenements WHERE date_debut > NOW() AND statut = 'publie'")->fetchColumn()
            ];

            // Cotisations
            $stats['subscriptions'] = [
                'total_paid' => (float)$pdo->query("SELECT COALESCE(SUM(montant), 0) FROM cotisations WHERE statut = 'paye'")->fetchColumn(),
                'active' => (int)$pdo->query("SELECT COUNT(*) FROM cotisations WHERE statut = 'paye' AND date_fin >= CURDATE()")->fetchColumn()
            ];

            jsonResponse(['success' => true, 'data' => $stats]);
            break;

        default:
            jsonError('Endpoint non trouvé', 404);
    }

} catch (Exception $e) {
    jsonError('Erreur serveur: ' . $e->getMessage(), 500);
}

// Logger la requête
logRequest($pdo, $apiKey['id'], $path, $method, http_response_code(), $startTime);

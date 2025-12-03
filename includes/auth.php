<?php
/**
 * Mini CRM - Fonctions d'authentification
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Récupère l'utilisateur connecté
 */
function getCurrentUser(): ?array {
    global $pdo;

    if (!isLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND actif = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Vérifie le rôle de l'utilisateur
 */
function hasRole(string $role): bool {
    $user = getCurrentUser();
    if (!$user) return false;

    $roles = ['membre' => 1, 'gestionnaire' => 2, 'admin' => 3];

    return isset($roles[$user['role']]) && $roles[$user['role']] >= $roles[$role];
}

/**
 * Vérifie si l'utilisateur est admin
 */
function isAdmin(): bool {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

/**
 * Vérifie si l'utilisateur est gestionnaire ou plus
 */
function isGestionnaire(): bool {
    return hasRole('gestionnaire');
}

/**
 * Connecte un utilisateur
 */
function login(string $email, string $password): array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        logLoginAttempt(null, false);
        return ['success' => false, 'message' => 'Email ou mot de passe incorrect.'];
    }

    if (!$user['actif']) {
        logLoginAttempt($user['id'], false);
        return ['success' => false, 'message' => 'Votre compte a été désactivé.'];
    }

    if (!password_verify($password, $user['password'])) {
        logLoginAttempt($user['id'], false);
        return ['success' => false, 'message' => 'Email ou mot de passe incorrect.'];
    }

    // Connexion réussie
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_nom'] = $user['nom'];
    $_SESSION['user_prenom'] = $user['prenom'];

    // Mettre à jour last_login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    logLoginAttempt($user['id'], true);

    return ['success' => true, 'user' => $user];
}

/**
 * Déconnecte l'utilisateur
 */
function logout(): void {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Enregistre une tentative de connexion
 */
function logLoginAttempt(?int $userId, bool $success): void {
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, success) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $success ? 1 : 0
    ]);
}

/**
 * Requiert une connexion
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '/index.php?error=login_required');
        exit;
    }
}

/**
 * Requiert un rôle minimum
 */
function requireRole(string $role): void {
    requireLogin();

    if (!hasRole($role)) {
        header('Location: ' . getBaseUrl() . '/dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Obtient l'URL de base
 */
function getBaseUrl(): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);

    // Remonter d'un niveau si on est dans un sous-dossier
    if (strpos($path, '/admin') !== false || strpos($path, '/member') !== false || strpos($path, '/gestionnaire') !== false) {
        $path = dirname($path);
    }

    return rtrim($protocol . '://' . $host . $path, '/');
}

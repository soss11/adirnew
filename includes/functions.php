<?php
/**
 * Mini CRM - Fonctions utilitaires
 */

/**
 * Récupère un paramètre depuis la base de données
 */
function getSetting(string $key, string $default = ''): string {
    global $pdo;

    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();

    return $result ? ($result['setting_value'] ?? $default) : $default;
}

/**
 * Sauvegarde un paramètre dans la base de données
 */
function setSetting(string $key, string $value): bool {
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

/**
 * Récupère tous les paramètres de l'association
 */
function getAssociationSettings(): array {
    return [
        'nom' => getSetting('asso_nom', 'Mon Association'),
        'adresse' => getSetting('asso_adresse', ''),
        'tel' => getSetting('asso_tel', ''),
        'email' => getSetting('asso_email', ''),
        'logo' => getSetting('asso_logo', '')
    ];
}

/**
 * Échappe une chaîne pour l'affichage HTML
 */
function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Génère un token CSRF
 */
function generateCsrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF
 */
function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Affiche un message flash
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Récupère et supprime le message flash
 */
function getFlashMessage(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Formate une date
 */
function formatDate(string $date, string $format = 'd/m/Y'): string {
    return date($format, strtotime($date));
}

/**
 * Formate une date avec l'heure
 */
function formatDateTime(string $date): string {
    return date('d/m/Y H:i', strtotime($date));
}

/**
 * Traduit un rôle en français
 */
function translateRole(string $role): string {
    $roles = [
        'admin' => 'Administrateur',
        'gestionnaire' => 'Gestionnaire',
        'membre' => 'Membre'
    ];
    return $roles[$role] ?? $role;
}

/**
 * Compte les utilisateurs par rôle
 */
function countUsersByRole(): array {
    global $pdo;

    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE actif = 1 GROUP BY role");
    $results = $stmt->fetchAll();

    $counts = ['admin' => 0, 'gestionnaire' => 0, 'membre' => 0, 'total' => 0];
    foreach ($results as $row) {
        $counts[$row['role']] = (int)$row['count'];
        $counts['total'] += (int)$row['count'];
    }

    return $counts;
}

/**
 * Génère un mot de passe aléatoire
 */
function generatePassword(int $length = 12): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Valide une adresse email
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Upload d'un fichier image
 */
function uploadImage(array $file, string $destination, int $maxSize = 2097152): array {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors de l\'upload.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Le fichier est trop volumineux (max 2 Mo).'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé.'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . strtolower($extension);
    $filepath = $destination . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Impossible de sauvegarder le fichier.'];
    }

    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

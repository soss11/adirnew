<?php
/**
 * Mini CRM - Script CRON pour l'envoi des rappels automatiques
 *
 * À exécuter quotidiennement via CRON:
 * 0 8 * * * php /path/to/cron/rappels.php
 */

// Empêcher l'exécution depuis le navigateur
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    die('Ce script ne peut être exécuté que via la ligne de commande.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== Mini CRM - Envoi des rappels automatiques ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$asso = getAssociationSettings();
$totalSent = 0;

// Récupérer les configurations actives
$configs = $pdo->query("SELECT * FROM rappels_config WHERE actif = 1")->fetchAll();

foreach ($configs as $config) {
    echo "Traitement: {$config['type_rappel']}...\n";
    $users = [];

    switch ($config['type_rappel']) {
        case 'cotisation_expiration':
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.*, c.date_fin, c.id as cotisation_id
                FROM users u
                JOIN cotisations c ON u.id = c.user_id
                WHERE c.statut = 'paye'
                AND c.date_fin = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND u.actif = 1
                AND NOT EXISTS (
                    SELECT 1 FROM rappels_logs r
                    WHERE r.user_id = u.id AND r.type_rappel = 'cotisation_expiration' AND r.reference_id = c.id
                )
            ");
            $stmt->execute([$config['delai_jours']]);
            $users = $stmt->fetchAll();
            break;

        case 'cotisation_retard':
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.*, c.date_fin, c.id as cotisation_id
                FROM users u
                JOIN cotisations c ON u.id = c.user_id
                WHERE c.statut = 'paye'
                AND c.date_fin = DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND u.actif = 1
                AND NOT EXISTS (
                    SELECT 1 FROM rappels_logs r
                    WHERE r.user_id = u.id AND r.type_rappel = 'cotisation_retard' AND DATE(r.envoye_at) = CURDATE()
                )
            ");
            $stmt->execute([$config['delai_jours']]);
            $users = $stmt->fetchAll();
            break;

        case 'evenement':
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
                    WHERE r.user_id = u.id AND r.type_rappel = 'evenement' AND r.reference_id = e.id
                )
            ");
            $stmt->execute([$config['delai_jours']]);
            $users = $stmt->fetchAll();
            break;

        case 'anniversaire':
            $stmt = $pdo->query("
                SELECT u.*
                FROM users u
                WHERE DATE_FORMAT(u.date_naissance, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
                AND u.actif = 1
                AND NOT EXISTS (
                    SELECT 1 FROM rappels_logs r
                    WHERE r.user_id = u.id AND r.type_rappel = 'anniversaire' AND YEAR(r.envoye_at) = YEAR(CURDATE())
                )
            ");
            $users = $stmt->fetchAll();
            break;
    }

    echo "  -> " . count($users) . " destinataire(s) trouvé(s)\n";

    foreach ($users as $user) {
        $sujet = $config['sujet'];
        $message = $config['template'];

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

        $headers = "From: " . ($asso['email'] ?: 'noreply@example.com') . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $sent = @mail($user['email'], $sujet, $message, $headers);

        $refId = $user['event_id'] ?? $user['cotisation_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO rappels_logs (type_rappel, user_id, reference_type, reference_id, statut, envoye_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $config['type_rappel'],
            $user['id'],
            $config['type_rappel'],
            $refId,
            $sent ? 'envoye' : 'erreur'
        ]);

        if ($sent) {
            $totalSent++;
            echo "     [OK] {$user['email']}\n";
        } else {
            echo "     [ERREUR] {$user['email']}\n";
        }
    }
}

echo "\n=== Terminé: {$totalSent} email(s) envoyé(s) ===\n";

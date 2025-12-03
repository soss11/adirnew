<?php
/**
 * Mini CRM - Téléchargement du modèle CSV
 */

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="modele-import-membres.csv"');

$output = fopen('php://output', 'w');

// BOM pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes
$headers = [
    'nom',
    'prenom',
    'email',
    'telephone',
    'adresse',
    'ville',
    'code_postal',
    'pays_origine',
    'nationalite',
    'langue_parlee',
    'date_naissance',
    'date_arrivee',
    'profession',
    'notes'
];

fputcsv($output, $headers, ';');

// Exemples de données
$exemples = [
    ['Dupont', 'Marie', 'marie.dupont@email.com', '0612345678', '123 rue de Paris', 'Paris', '75001', 'France', 'Française', 'Français, Anglais', '1985-03-15', '2020-01-01', 'Enseignante', 'Membre actif'],
    ['Martin', 'Jean', 'jean.martin@email.com', '0623456789', '45 avenue des Champs', 'Lyon', '69001', 'Belgique', 'Belge', 'Français, Néerlandais', '1978-07-22', '2018-06-15', 'Ingénieur', ''],
    ['Tremblay', 'Sophie', 'sophie.tremblay@email.com', '0634567890', '78 boulevard Saint-Michel', 'Marseille', '13001', 'Canada', 'Canadienne', 'Français, Anglais', '1990-11-08', '2021-09-01', 'Graphiste', 'Bénévole occasionnelle']
];

foreach ($exemples as $exemple) {
    fputcsv($output, $exemple, ';');
}

fclose($output);
exit;

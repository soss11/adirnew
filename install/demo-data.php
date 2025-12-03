<?php
/**
 * Mini CRM - Génération de données de démonstration
 * Inclut les nouvelles fonctionnalités : familles, fidélité, feedbacks, etc.
 */

require_once __DIR__ . '/../includes/db.php';

$messages = [];

// Fonction pour générer un mot de passe hashé
function hashPass($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Vérifier si les tables existent
try {
    $pdo->query("SELECT 1 FROM familles LIMIT 1");
} catch (PDOException $e) {
    die("Veuillez d'abord exécuter le script de mise à jour : <a href='upgrade.php'>upgrade.php</a>");
}

// ============================================
// FAMILLES DE DÉMONSTRATION
// ============================================
$familles = [
    ['nom' => 'Dupont', 'adresse' => '15 rue des Lilas, 75011 Paris', 'telephone' => '01 23 45 67 89', 'email' => 'famille.dupont@email.com'],
    ['nom' => 'Martin', 'adresse' => '8 avenue Victor Hugo, 75016 Paris', 'telephone' => '01 34 56 78 90', 'email' => 'martin.famille@email.com'],
    ['nom' => 'Diallo', 'adresse' => '42 rue de la Paix, 75002 Paris', 'telephone' => '01 45 67 89 01', 'email' => 'diallo@email.com'],
];

foreach ($familles as $f) {
    $stmt = $pdo->prepare("INSERT INTO familles (nom_famille, adresse, telephone, email, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$f['nom'], $f['adresse'], $f['telephone'], $f['email']]);
}
$messages[] = "✓ 3 familles créées";

// Récupérer les IDs des familles
$familleIds = $pdo->query("SELECT id, nom_famille FROM familles")->fetchAll(PDO::FETCH_KEY_PAIR);

// ============================================
// MEMBRES SUPPLÉMENTAIRES
// ============================================
$membresDemo = [
    // Famille Dupont
    ['email' => 'pierre.dupont@email.com', 'nom' => 'Dupont', 'prenom' => 'Pierre', 'telephone' => '06 11 22 33 44', 'ville' => 'Paris', 'pays_origine' => 'France', 'date_naissance' => '1975-03-15', 'profession' => 'Ingénieur', 'famille' => 'Dupont', 'chef' => true],
    ['email' => 'marie.dupont@email.com', 'nom' => 'Dupont', 'prenom' => 'Marie', 'telephone' => '06 11 22 33 45', 'ville' => 'Paris', 'pays_origine' => 'France', 'date_naissance' => '1978-07-22', 'profession' => 'Médecin', 'famille' => 'Dupont'],
    ['email' => 'lucas.dupont@email.com', 'nom' => 'Dupont', 'prenom' => 'Lucas', 'telephone' => '06 11 22 33 46', 'ville' => 'Paris', 'pays_origine' => 'France', 'date_naissance' => '2005-09-10', 'profession' => 'Étudiant', 'famille' => 'Dupont'],

    // Famille Martin
    ['email' => 'jean.martin@email.com', 'nom' => 'Martin', 'prenom' => 'Jean', 'telephone' => '06 22 33 44 55', 'ville' => 'Paris', 'pays_origine' => 'Belgique', 'date_naissance' => '1980-01-20', 'profession' => 'Architecte', 'famille' => 'Martin', 'chef' => true],
    ['email' => 'sophie.martin@email.com', 'nom' => 'Martin', 'prenom' => 'Sophie', 'telephone' => '06 22 33 44 56', 'ville' => 'Paris', 'pays_origine' => 'France', 'date_naissance' => '1982-11-05', 'profession' => 'Avocate', 'famille' => 'Martin'],

    // Famille Diallo
    ['email' => 'amadou.diallo@email.com', 'nom' => 'Diallo', 'prenom' => 'Amadou', 'telephone' => '06 33 44 55 66', 'ville' => 'Paris', 'pays_origine' => 'Sénégal', 'date_naissance' => '1970-06-12', 'profession' => 'Commerçant', 'famille' => 'Diallo', 'chef' => true, 'benevole' => true, 'competences' => 'Cuisine, Organisation'],
    ['email' => 'fatou.diallo@email.com', 'nom' => 'Diallo', 'prenom' => 'Fatou', 'telephone' => '06 33 44 55 67', 'ville' => 'Paris', 'pays_origine' => 'Sénégal', 'date_naissance' => '1975-04-25', 'profession' => 'Infirmière', 'famille' => 'Diallo', 'benevole' => true, 'competences' => 'Santé, Premiers secours'],
    ['email' => 'ibrahima.diallo@email.com', 'nom' => 'Diallo', 'prenom' => 'Ibrahima', 'telephone' => '06 33 44 55 68', 'ville' => 'Paris', 'pays_origine' => 'Sénégal', 'date_naissance' => '2000-12-01', 'profession' => 'Étudiant', 'famille' => 'Diallo'],

    // Membres sans famille
    ['email' => 'alice.bernard@email.com', 'nom' => 'Bernard', 'prenom' => 'Alice', 'telephone' => '06 44 55 66 77', 'ville' => 'Lyon', 'pays_origine' => 'France', 'date_naissance' => '1990-08-18', 'profession' => 'Designer', 'benevole' => true, 'competences' => 'Communication, Design'],
    ['email' => 'omar.benali@email.com', 'nom' => 'Benali', 'prenom' => 'Omar', 'telephone' => '06 55 66 77 88', 'ville' => 'Marseille', 'pays_origine' => 'Maroc', 'date_naissance' => '1985-02-28', 'profession' => 'Développeur'],
    ['email' => 'chen.wei@email.com', 'nom' => 'Wei', 'prenom' => 'Chen', 'telephone' => '06 66 77 88 99', 'ville' => 'Paris', 'pays_origine' => 'Chine', 'date_naissance' => '1988-10-10', 'profession' => 'Comptable'],
    ['email' => 'maria.silva@email.com', 'nom' => 'Silva', 'prenom' => 'Maria', 'telephone' => '06 77 88 99 00', 'ville' => 'Bordeaux', 'pays_origine' => 'Portugal', 'date_naissance' => '1992-05-14', 'profession' => 'Professeure', 'benevole' => true, 'competences' => 'Langues, Enseignement'],
];

foreach ($membresDemo as $m) {
    $familleId = isset($m['famille']) ? array_search($m['famille'], array_flip($familleIds)) : null;
    if ($familleId === false) $familleId = null;

    $stmt = $pdo->prepare("INSERT INTO users (email, password, nom, prenom, role, telephone, ville, pays_origine, date_naissance, profession, famille_id, actif, est_benevole, competences_benevole, annuaire_visible, points_fidelite, created_at)
                           VALUES (?, ?, ?, ?, 'membre', ?, ?, ?, ?, ?, ?, 1, ?, ?, 1, ?, NOW())");
    $points = rand(0, 500);
    $stmt->execute([
        $m['email'],
        hashPass('demo123'),
        $m['nom'],
        $m['prenom'],
        $m['telephone'],
        $m['ville'],
        $m['pays_origine'],
        $m['date_naissance'],
        $m['profession'],
        $familleId,
        isset($m['benevole']) ? 1 : 0,
        $m['competences'] ?? null,
        $points
    ]);

    $userId = $pdo->lastInsertId();

    // Mettre à jour le chef de famille
    if (isset($m['chef']) && $m['chef'] && $familleId) {
        $stmt = $pdo->prepare("UPDATE familles SET chef_famille_id = ? WHERE nom_famille = ?");
        $stmt->execute([$userId, $m['famille']]);
    }

    // Ajouter des transactions fidélité
    if ($points > 0) {
        $stmt = $pdo->prepare("INSERT INTO fidelite_transactions (user_id, points, type_transaction, description, reference_type, created_at) VALUES (?, ?, 'gain', 'Points de bienvenue', 'manuel', NOW())");
        $stmt->execute([$userId, $points]);
    }
}
$messages[] = "✓ " . count($membresDemo) . " membres créés";

// ============================================
// RÉCOMPENSES FIDÉLITÉ
// ============================================
$recompenses = [
    ['nom' => 'Réduction 5€', 'description' => 'Réduction de 5€ sur votre prochaine cotisation', 'points' => 200, 'type' => 'reduction', 'valeur' => 5],
    ['nom' => 'Réduction 10€', 'description' => 'Réduction de 10€ sur un événement', 'points' => 400, 'type' => 'reduction', 'valeur' => 10],
    ['nom' => 'Place gratuite', 'description' => 'Une place gratuite pour un événement', 'points' => 600, 'type' => 'cadeau', 'valeur' => 0],
    ['nom' => 'Accès VIP', 'description' => 'Accès VIP pour le prochain gala annuel', 'points' => 1000, 'type' => 'acces_vip', 'valeur' => 0, 'stock' => 10],
    ['nom' => 'Panier garni', 'description' => 'Un panier garni de produits locaux', 'points' => 800, 'type' => 'cadeau', 'valeur' => 0, 'stock' => 5],
];

foreach ($recompenses as $r) {
    $stmt = $pdo->prepare("INSERT INTO fidelite_recompenses (nom, description, points_requis, type_recompense, valeur_reduction, stock, actif, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$r['nom'], $r['description'], $r['points'], $r['type'], $r['valeur'], $r['stock'] ?? -1]);
}
$messages[] = "✓ " . count($recompenses) . " récompenses fidélité créées";

// ============================================
// ÉVÉNEMENTS DE DÉMONSTRATION
// ============================================
$evenements = [
    ['titre' => 'Fête de l\'Association 2024', 'type' => 'soiree', 'description' => 'Grande fête annuelle avec buffet, musique et tombola', 'date' => date('Y-m-d', strtotime('+30 days')), 'lieu' => 'Salle des fêtes', 'places' => 150, 'prix_m' => 15, 'prix_nm' => 25, 'statut' => 'publie'],
    ['titre' => 'Atelier Cuisine du Monde', 'type' => 'atelier', 'description' => 'Découvrez les saveurs du monde avec nos chefs amateurs', 'date' => date('Y-m-d', strtotime('+14 days')), 'lieu' => 'Cuisine collective', 'places' => 20, 'prix_m' => 10, 'prix_nm' => 15, 'statut' => 'publie'],
    ['titre' => 'Soirée Karaoké', 'type' => 'soiree', 'description' => 'Venez chanter vos chansons préférées !', 'date' => date('Y-m-d', strtotime('+45 days')), 'lieu' => 'Bar Le Central', 'places' => 50, 'prix_m' => 5, 'prix_nm' => 8, 'statut' => 'publie'],
    ['titre' => 'Réunion Mensuelle', 'type' => 'reunion', 'description' => 'Réunion des membres - ordre du jour à venir', 'date' => date('Y-m-d', strtotime('+7 days')), 'lieu' => 'Local associatif', 'places' => 30, 'prix_m' => 0, 'prix_nm' => 0, 'statut' => 'publie'],
    ['titre' => 'Spectacle de fin d\'année (passé)', 'type' => 'spectacle', 'description' => 'Spectacle de danse et musique traditionnelle', 'date' => date('Y-m-d', strtotime('-30 days')), 'lieu' => 'Théâtre Municipal', 'places' => 200, 'prix_m' => 20, 'prix_nm' => 30, 'statut' => 'termine'],
    ['titre' => 'Tombola de Noël (passé)', 'type' => 'tombola', 'description' => 'Grande tombola avec de nombreux lots', 'date' => date('Y-m-d', strtotime('-60 days')), 'lieu' => 'Place centrale', 'places' => 0, 'prix_m' => 5, 'prix_nm' => 5, 'statut' => 'termine'],
];

foreach ($evenements as $e) {
    $stmt = $pdo->prepare("INSERT INTO evenements (titre, description, type, date_debut, lieu, places_max, prix_membre, prix_non_membre, statut, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$e['titre'], $e['description'], $e['type'], $e['date'] . ' 19:00:00', $e['lieu'], $e['places'], $e['prix_m'], $e['prix_nm'], $e['statut']]);
}
$messages[] = "✓ " . count($evenements) . " événements créés";

// ============================================
// FEEDBACKS POUR LES ÉVÉNEMENTS PASSÉS
// ============================================
$eventIds = $pdo->query("SELECT id FROM evenements WHERE statut = 'termine'")->fetchAll(PDO::FETCH_COLUMN);
$memberIds = $pdo->query("SELECT id FROM users WHERE role = 'membre' LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);

$feedbackComments = [
    'positifs' => ['Excellente organisation', 'Ambiance très conviviale', 'Repas délicieux', 'Animations variées', 'Accueil chaleureux'],
    'ameliorer' => ['Sonorisation à améliorer', 'Plus de places assises', 'Commencer plus tôt', 'Plus de variété musicale', 'Mieux indiquer le parking'],
    'suggestions' => ['Organiser plus souvent', 'Inviter des artistes locaux', 'Faire un événement pour enfants', 'Proposer des options végétariennes', 'Créer un groupe WhatsApp']
];

foreach ($eventIds as $eventId) {
    $nbFeedbacks = rand(5, 12);
    shuffle($memberIds);

    for ($i = 0; $i < min($nbFeedbacks, count($memberIds)); $i++) {
        $stmt = $pdo->prepare("INSERT INTO event_feedback (evenement_id, user_id, note_globale, note_organisation, note_lieu, note_animation, points_positifs, points_ameliorer, suggestions, recommanderait, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $eventId,
            $memberIds[$i],
            rand(3, 5),
            rand(3, 5),
            rand(2, 5),
            rand(3, 5),
            $feedbackComments['positifs'][array_rand($feedbackComments['positifs'])],
            $feedbackComments['ameliorer'][array_rand($feedbackComments['ameliorer'])],
            rand(0, 1) ? $feedbackComments['suggestions'][array_rand($feedbackComments['suggestions'])] : null,
            rand(0, 1)
        ]);
    }
}
$messages[] = "✓ Feedbacks générés pour les événements passés";

// ============================================
// TYPES DE COTISATION
// ============================================
$cotisationTypes = [
    ['nom' => 'Cotisation Individuelle', 'montant' => 30, 'duree' => 12],
    ['nom' => 'Cotisation Famille', 'montant' => 50, 'duree' => 12],
    ['nom' => 'Cotisation Étudiant', 'montant' => 15, 'duree' => 12],
    ['nom' => 'Cotisation Soutien', 'montant' => 100, 'duree' => 12],
];

foreach ($cotisationTypes as $ct) {
    $stmt = $pdo->prepare("INSERT INTO cotisation_types (nom, montant, duree_mois, actif, created_at) VALUES (?, ?, ?, 1, NOW())");
    $stmt->execute([$ct['nom'], $ct['montant'], $ct['duree']]);
}
$messages[] = "✓ " . count($cotisationTypes) . " types de cotisation créés";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Données de démonstration - Mini CRM</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f5f5; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a56db; margin-bottom: 20px; }
        .message { padding: 10px 0; border-bottom: 1px solid #eee; color: #16a34a; }
        .btn { display: inline-block; padding: 12px 24px; background: #1a56db; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        .btn:hover { background: #1e40af; }
        .info-box { background: #f0f7ff; border: 1px solid #bfdbfe; padding: 15px; border-radius: 8px; margin-top: 20px; }
        .info-box h4 { margin: 0 0 10px; color: #1a56db; }
        .info-box p { margin: 5px 0; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Données de démonstration</h1>

        <?php foreach ($messages as $msg): ?>
            <div class="message"><?php echo $msg; ?></div>
        <?php endforeach; ?>

        <div class="info-box">
            <h4>Comptes de test créés</h4>
            <p><strong>Mot de passe pour tous les nouveaux membres :</strong> demo123</p>
            <p>Vous pouvez vous connecter avec n'importe quel email de membre.</p>
        </div>

        <div class="info-box">
            <h4>Données générées</h4>
            <p>• 3 familles avec membres associés</p>
            <p>• 12 nouveaux membres avec profils complets</p>
            <p>• 5 récompenses fidélité</p>
            <p>• 6 événements (4 à venir, 2 passés)</p>
            <p>• Feedbacks pour les événements passés</p>
            <p>• 4 types de cotisation</p>
        </div>

        <a href="../dashboard.php" class="btn">Aller au tableau de bord</a>
    </div>
</body>
</html>

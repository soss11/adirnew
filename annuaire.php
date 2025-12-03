<?php
/**
 * Mini CRM - Annuaire des membres
 * Accessible aux membres connectés
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$search = trim($_GET['q'] ?? '');
$filterVille = $_GET['ville'] ?? '';
$filterPays = $_GET['pays'] ?? '';
$filterCompetence = $_GET['competence'] ?? '';

// Construire la requête
$sql = "SELECT id, nom, prenom, email, telephone, ville, pays_origine, profession, photo, est_benevole, competences_benevole
        FROM users
        WHERE actif = 1 AND annuaire_visible = 1 AND role = 'membre'";
$params = [];

if ($search) {
    $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR ville LIKE ? OR profession LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($filterVille) {
    $sql .= " AND ville = ?";
    $params[] = $filterVille;
}

if ($filterPays) {
    $sql .= " AND pays_origine = ?";
    $params[] = $filterPays;
}

if ($filterCompetence) {
    $sql .= " AND competences_benevole LIKE ?";
    $params[] = "%$filterCompetence%";
}

$sql .= " ORDER BY nom, prenom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$membres = $stmt->fetchAll();

// Récupérer les filtres disponibles
$villes = $pdo->query("SELECT DISTINCT ville FROM users WHERE actif = 1 AND annuaire_visible = 1 AND ville IS NOT NULL AND ville != '' ORDER BY ville")->fetchAll(PDO::FETCH_COLUMN);
$pays = $pdo->query("SELECT DISTINCT pays_origine FROM users WHERE actif = 1 AND annuaire_visible = 1 AND pays_origine IS NOT NULL AND pays_origine != '' ORDER BY pays_origine")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Annuaire des membres';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.search-bar {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}
.search-bar input[type="text"] {
    flex: 1;
    min-width: 250px;
    padding: 12px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 25px;
    font-size: 16px;
}
.search-bar input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
}
.search-bar select {
    padding: 12px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 25px;
    background: #fff;
    min-width: 150px;
}
.members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.member-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    transition: all 0.2s;
}
.member-card:hover {
    border-color: var(--primary);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transform: translateY(-3px);
}
.member-card .avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 600;
    margin: 0 auto 15px;
    overflow: hidden;
}
.member-card .avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.member-card h3 {
    margin: 0 0 5px;
    font-size: 18px;
}
.member-card .profession {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 15px;
}
.member-card .info {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 14px;
    color: #4b5563;
}
.member-card .info span {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.member-card .badges {
    display: flex;
    gap: 5px;
    justify-content: center;
    margin-top: 15px;
    flex-wrap: wrap;
}
.member-card .badge {
    font-size: 11px;
    padding: 3px 10px;
}
.contact-btn {
    display: inline-block;
    margin-top: 15px;
    padding: 8px 20px;
    background: var(--primary);
    color: #fff;
    text-decoration: none;
    border-radius: 20px;
    font-size: 14px;
    transition: background 0.2s;
}
.contact-btn:hover {
    background: #1e40af;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}
.empty-state .icon { font-size: 64px; margin-bottom: 20px; }
.stats-bar {
    display: flex;
    gap: 30px;
    margin-bottom: 20px;
    color: #6b7280;
}
.stats-bar span { display: flex; align-items: center; gap: 8px; }
</style>

<div class="card">
    <div class="card-header">
        <h3>Annuaire des membres</h3>
    </div>
    <div class="card-body">
        <!-- Barre de recherche -->
        <form method="GET" class="search-bar">
            <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Rechercher un membre (nom, ville, profession...)">
            <select name="ville">
                <option value="">Toutes les villes</option>
                <?php foreach ($villes as $v): ?>
                    <option value="<?php echo e($v); ?>" <?php echo $filterVille === $v ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="pays">
                <option value="">Tous les pays</option>
                <?php foreach ($pays as $p): ?>
                    <option value="<?php echo e($p); ?>" <?php echo $filterPays === $p ? 'selected' : ''; ?>><?php echo e($p); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Rechercher</button>
            <?php if ($search || $filterVille || $filterPays): ?>
                <a href="annuaire.php" class="btn btn-secondary">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <!-- Stats -->
        <div class="stats-bar">
            <span>&#128100; <?php echo count($membres); ?> membre(s) trouvé(s)</span>
            <?php if ($search || $filterVille || $filterPays): ?>
                <span>&#128269; Filtres actifs</span>
            <?php endif; ?>
        </div>

        <!-- Grille de membres -->
        <?php if (empty($membres)): ?>
            <div class="empty-state">
                <div class="icon">&#128100;</div>
                <h4>Aucun membre trouvé</h4>
                <p>Essayez de modifier vos critères de recherche.</p>
            </div>
        <?php else: ?>
            <div class="members-grid">
                <?php foreach ($membres as $m): ?>
                    <div class="member-card">
                        <div class="avatar">
                            <?php if ($m['photo']): ?>
                                <img src="<?php echo getBaseUrl(); ?>/assets/uploads/<?php echo e($m['photo']); ?>" alt="">
                            <?php else: ?>
                                <?php echo e(strtoupper(substr($m['prenom'] ?: $m['nom'], 0, 1))); ?>
                            <?php endif; ?>
                        </div>

                        <h3><?php echo e($m['prenom'] . ' ' . $m['nom']); ?></h3>

                        <?php if ($m['profession']): ?>
                            <div class="profession"><?php echo e($m['profession']); ?></div>
                        <?php endif; ?>

                        <div class="info">
                            <?php if ($m['ville']): ?>
                                <span>&#128205; <?php echo e($m['ville']); ?><?php echo $m['pays_origine'] ? ', ' . e($m['pays_origine']) : ''; ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($m['est_benevole'] && $m['competences_benevole']): ?>
                            <div class="badges">
                                <span class="badge badge-success">Bénévole</span>
                                <?php
                                $competences = explode(',', $m['competences_benevole']);
                                foreach (array_slice($competences, 0, 2) as $c):
                                    $c = trim($c);
                                    if ($c):
                                ?>
                                    <span class="badge"><?php echo e($c); ?></span>
                                <?php endif; endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($m['email'] && $m['id'] != $currentUser['id']): ?>
                            <a href="mailto:<?php echo e($m['email']); ?>" class="contact-btn">Contacter</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

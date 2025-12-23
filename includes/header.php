<?php
/**
 * Mini CRM - Header
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser();
$asso = getAssociationSettings();
$flash = getFlashMessage();

// Déterminer la page active
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Groupes de pages pour l'accordéon
$menuGroups = [
    'gestion' => ['membres', 'evenements', 'calendrier', 'cotisations'],
    'outils' => ['checkin', 'benevoles', 'reunions', 'inventaire', 'sondages'],
    'communication' => ['emails', 'rappels', 'social'],
    'finances' => ['depenses', 'rapport-financier'],
    'plus' => ['galerie', 'familles', 'fidelite', 'covoiturage', 'feedback', 'plan-salle'],
    'admin' => ['users', 'settings', 'statistiques', 'import', 'api']
];

// Trouver quel groupe est actif
$activeGroup = '';
foreach ($menuGroups as $group => $pages) {
    if (in_array($currentPage, $pages)) {
        $activeGroup = $group;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo e($asso['nom'] ?: 'Mini CRM'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle" aria-label="Menu">
        <span></span>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <?php if (!empty($asso['logo'])): ?>
                    <img src="<?php echo getBaseUrl(); ?>/assets/uploads/<?php echo e($asso['logo']); ?>" alt="Logo" class="logo">
                <?php else: ?>
                    <div class="logo-placeholder"><?php echo e(substr($asso['nom'] ?: 'MC', 0, 2)); ?></div>
                <?php endif; ?>
                <h2><?php echo e($asso['nom'] ?: 'Mini CRM'); ?></h2>
            </div>

            <nav class="sidebar-nav">
                <a href="<?php echo getBaseUrl(); ?>/dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <span class="nav-icon">🏠</span>
                    Tableau de bord
                </a>

                <?php if (isGestionnaire()): ?>
                    <!-- GESTION -->
                    <div class="nav-group <?php echo $activeGroup === 'gestion' ? 'open' : ''; ?>">
                        <div class="nav-group-header" onclick="toggleNavGroup(this)">
                            <span class="nav-icon">👥</span>
                            <span class="nav-group-title">Gestion</span>
                            <span class="nav-arrow">›</span>
                        </div>
                        <div class="nav-group-items">
                            <a href="<?php echo getBaseUrl(); ?>/admin/membres.php" class="nav-item <?php echo $currentPage === 'membres' ? 'active' : ''; ?>">Membres</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/evenements.php" class="nav-item <?php echo $currentPage === 'evenements' || $currentPage === 'calendrier' ? 'active' : ''; ?>">Événements</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/cotisations.php" class="nav-item <?php echo $currentPage === 'cotisations' ? 'active' : ''; ?>">Cotisations</a>
                        </div>
                    </div>

                    <!-- OUTILS -->
                    <div class="nav-group <?php echo $activeGroup === 'outils' ? 'open' : ''; ?>">
                        <div class="nav-group-header" onclick="toggleNavGroup(this)">
                            <span class="nav-icon">🛠️</span>
                            <span class="nav-group-title">Outils</span>
                            <span class="nav-arrow">›</span>
                        </div>
                        <div class="nav-group-items">
                            <a href="<?php echo getBaseUrl(); ?>/admin/checkin.php" class="nav-item <?php echo $currentPage === 'checkin' ? 'active' : ''; ?>">Check-in</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/benevoles.php" class="nav-item <?php echo $currentPage === 'benevoles' ? 'active' : ''; ?>">Bénévoles</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/reunions.php" class="nav-item <?php echo $currentPage === 'reunions' ? 'active' : ''; ?>">Réunions</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/inventaire.php" class="nav-item <?php echo $currentPage === 'inventaire' ? 'active' : ''; ?>">Inventaire</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/sondages.php" class="nav-item <?php echo $currentPage === 'sondages' ? 'active' : ''; ?>">Sondages</a>
                        </div>
                    </div>

                    <!-- COMMUNICATION -->
                    <div class="nav-group <?php echo $activeGroup === 'communication' ? 'open' : ''; ?>">
                        <div class="nav-group-header" onclick="toggleNavGroup(this)">
                            <span class="nav-icon">📧</span>
                            <span class="nav-group-title">Communication</span>
                            <span class="nav-arrow">›</span>
                        </div>
                        <div class="nav-group-items">
                            <a href="<?php echo getBaseUrl(); ?>/admin/emails.php" class="nav-item <?php echo $currentPage === 'emails' ? 'active' : ''; ?>">Emails</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/rappels.php" class="nav-item <?php echo $currentPage === 'rappels' ? 'active' : ''; ?>">Rappels</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/social.php" class="nav-item <?php echo $currentPage === 'social' ? 'active' : ''; ?>">Réseaux sociaux</a>
                        </div>
                    </div>

                    <!-- FINANCES -->
                    <div class="nav-group <?php echo $activeGroup === 'finances' ? 'open' : ''; ?>">
                        <div class="nav-group-header" onclick="toggleNavGroup(this)">
                            <span class="nav-icon">💰</span>
                            <span class="nav-group-title">Finances</span>
                            <span class="nav-arrow">›</span>
                        </div>
                        <div class="nav-group-items">
                            <a href="<?php echo getBaseUrl(); ?>/admin/depenses.php" class="nav-item <?php echo $currentPage === 'depenses' ? 'active' : ''; ?>">Dépenses</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/rapport-financier.php" class="nav-item <?php echo $currentPage === 'rapport-financier' ? 'active' : ''; ?>">Rapports</a>
                        </div>
                    </div>

                    <!-- PLUS -->
                    <div class="nav-group <?php echo $activeGroup === 'plus' ? 'open' : ''; ?>">
                        <div class="nav-group-header" onclick="toggleNavGroup(this)">
                            <span class="nav-icon">📦</span>
                            <span class="nav-group-title">Plus</span>
                            <span class="nav-arrow">›</span>
                        </div>
                        <div class="nav-group-items">
                            <a href="<?php echo getBaseUrl(); ?>/admin/galerie.php" class="nav-item <?php echo $currentPage === 'galerie' ? 'active' : ''; ?>">Galerie</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/familles.php" class="nav-item <?php echo $currentPage === 'familles' ? 'active' : ''; ?>">Familles</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/fidelite.php" class="nav-item <?php echo $currentPage === 'fidelite' ? 'active' : ''; ?>">Fidélité</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/covoiturage.php" class="nav-item <?php echo $currentPage === 'covoiturage' ? 'active' : ''; ?>">Covoiturage</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/feedback.php" class="nav-item <?php echo $currentPage === 'feedback' ? 'active' : ''; ?>">Feedbacks</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/plan-salle.php" class="nav-item <?php echo $currentPage === 'plan-salle' ? 'active' : ''; ?>">Plans de salle</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isAdmin()): ?>
                    <!-- ADMINISTRATION -->
                    <div class="nav-group <?php echo $activeGroup === 'admin' ? 'open' : ''; ?>">
                        <div class="nav-group-header" onclick="toggleNavGroup(this)">
                            <span class="nav-icon">⚙️</span>
                            <span class="nav-group-title">Administration</span>
                            <span class="nav-arrow">›</span>
                        </div>
                        <div class="nav-group-items">
                            <a href="<?php echo getBaseUrl(); ?>/admin/users.php" class="nav-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>">Utilisateurs</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/settings.php" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">Paramètres</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/statistiques.php" class="nav-item <?php echo $currentPage === 'statistiques' ? 'active' : ''; ?>">Statistiques</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/import.php" class="nav-item <?php echo $currentPage === 'import' ? 'active' : ''; ?>">Import</a>
                            <a href="<?php echo getBaseUrl(); ?>/admin/api.php" class="nav-item <?php echo $currentPage === 'api' ? 'active' : ''; ?>">API</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="nav-separator"></div>

                <a href="<?php echo getBaseUrl(); ?>/annuaire.php" class="nav-item <?php echo $currentPage === 'annuaire' ? 'active' : ''; ?>">
                    <span class="nav-icon">📖</span>
                    Annuaire
                </a>

                <a href="<?php echo getBaseUrl(); ?>/profile.php" class="nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="nav-icon">👤</span>
                    Mon profil
                </a>

                <a href="<?php echo getBaseUrl(); ?>/logout.php" class="nav-item nav-logout">
                    <span class="nav-icon">🚪</span>
                    Déconnexion
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?php echo e(substr($currentUser['prenom'] ?? $currentUser['nom'], 0, 1)); ?></div>
                    <div class="user-details">
                        <span class="user-name"><?php echo e($currentUser['prenom'] . ' ' . $currentUser['nom']); ?></span>
                        <span class="user-role"><?php echo translateRole($currentUser['role']); ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h1><?php echo isset($pageTitle) ? e($pageTitle) : 'Tableau de bord'; ?></h1>
            </header>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo e($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="content">

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
                    <span class="nav-icon">&#9673;</span>
                    Tableau de bord
                </a>

                <?php if (isGestionnaire()): ?>
                    <div class="nav-separator"></div>
                    <div class="nav-section-title">Gestion</div>

                    <a href="<?php echo getBaseUrl(); ?>/admin/membres.php" class="nav-item <?php echo $currentPage === 'membres' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9787;</span>
                        Membres
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/evenements.php" class="nav-item <?php echo $currentPage === 'evenements' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9734;</span>
                        Événements
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/calendrier.php" class="nav-item <?php echo $currentPage === 'calendrier' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128197;</span>
                        Calendrier
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/cotisations.php" class="nav-item <?php echo $currentPage === 'cotisations' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#8364;</span>
                        Cotisations
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/benevoles.php" class="nav-item <?php echo $currentPage === 'benevoles' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9996;</span>
                        Bénévoles
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/checkin.php" class="nav-item <?php echo $currentPage === 'checkin' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128244;</span>
                        Check-in
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/galerie.php" class="nav-item <?php echo $currentPage === 'galerie' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128247;</span>
                        Galerie photos
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/feedback.php" class="nav-item <?php echo $currentPage === 'feedback' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128172;</span>
                        Feedbacks
                    </a>

                    <div class="nav-separator"></div>
                    <div class="nav-section-title">Membres avancés</div>

                    <a href="<?php echo getBaseUrl(); ?>/admin/familles.php" class="nav-item <?php echo $currentPage === 'familles' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128106;</span>
                        Familles
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/fidelite.php" class="nav-item <?php echo $currentPage === 'fidelite' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#127942;</span>
                        Fidélité
                    </a>

                    <div class="nav-separator"></div>
                    <div class="nav-section-title">Communication</div>

                    <a href="<?php echo getBaseUrl(); ?>/admin/emails.php" class="nav-item <?php echo $currentPage === 'emails' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128231;</span>
                        Emails / Newsletter
                    </a>

                    <div class="nav-separator"></div>
                    <div class="nav-section-title">Finances</div>

                    <a href="<?php echo getBaseUrl(); ?>/admin/depenses.php" class="nav-item <?php echo $currentPage === 'depenses' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128176;</span>
                        Dépenses
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/rapport-financier.php" class="nav-item <?php echo $currentPage === 'rapport-financier' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128202;</span>
                        Rapport financier
                    </a>
                <?php endif; ?>

                <?php if (isAdmin()): ?>
                    <div class="nav-separator"></div>
                    <div class="nav-section-title">Administration</div>

                    <a href="<?php echo getBaseUrl(); ?>/admin/users.php" class="nav-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9881;</span>
                        Utilisateurs
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/settings.php" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9881;</span>
                        Paramètres
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/statistiques.php" class="nav-item <?php echo $currentPage === 'statistiques' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128200;</span>
                        Statistiques
                    </a>

                    <a href="<?php echo getBaseUrl(); ?>/admin/import.php" class="nav-item <?php echo $currentPage === 'import' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#128229;</span>
                        Import CSV
                    </a>
                <?php endif; ?>

                <div class="nav-separator"></div>

                <a href="<?php echo getBaseUrl(); ?>/annuaire.php" class="nav-item <?php echo $currentPage === 'annuaire' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#128218;</span>
                    Annuaire membres
                </a>

                <a href="<?php echo getBaseUrl(); ?>/profile.php" class="nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#9998;</span>
                    Mon profil
                </a>

                <a href="<?php echo getBaseUrl(); ?>/logout.php" class="nav-item nav-logout">
                    <span class="nav-icon">&#10140;</span>
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

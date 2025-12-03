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
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
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

                    <a href="<?php echo getBaseUrl(); ?>/admin/cotisations.php" class="nav-item <?php echo $currentPage === 'cotisations' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#8364;</span>
                        Cotisations
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
                <?php endif; ?>

                <div class="nav-separator"></div>

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

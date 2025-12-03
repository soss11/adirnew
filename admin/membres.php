<?php
/**
 * Mini CRM - Gestion des membres
 */

$pageTitle = 'Membres';
require_once __DIR__ . '/../includes/header.php';

requireRole('gestionnaire');

$error = '';
$action = $_GET['action'] ?? '';
$memberId = (int)($_GET['id'] ?? 0);

// Supprimer un membre
if ($action === 'delete' && $memberId > 0) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$memberId]);
    $target = $stmt->fetch();

    if ($target && $target['role'] === 'membre') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'membre'");
        $stmt->execute([$memberId]);
        setFlashMessage('success', 'Membre supprimé avec succès.');
    }
    header('Location: membres.php');
    exit;
}

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $code_postal = trim($_POST['code_postal'] ?? '');
    $pays_origine = trim($_POST['pays_origine'] ?? '');
    $nationalite = trim($_POST['nationalite'] ?? '');
    $langue_parlee = trim($_POST['langue_parlee'] ?? '');
    $date_arrivee = $_POST['date_arrivee'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $profession = trim($_POST['profession'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($nom)) {
        $error = 'L\'email et le nom sont obligatoires.';
    } elseif (!isValidEmail($email)) {
        $error = 'Adresse email invalide.';
    } elseif ($editId === 0 && empty($password)) {
        $error = 'Le mot de passe est obligatoire pour un nouveau membre.';
    } else {
        // Vérifier email unique
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $editId]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        }
    }

    // Upload photo
    $photoFilename = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $result = uploadImage($_FILES['photo'], $uploadDir);
        if ($result['success']) {
            $photoFilename = $result['filename'];
        } else {
            $error = $result['message'];
        }
    }

    if (empty($error)) {
        try {
            if ($editId > 0) {
                // Modification
                $sql = "UPDATE users SET email = ?, nom = ?, prenom = ?, telephone = ?, adresse = ?,
                        ville = ?, code_postal = ?, pays_origine = ?, nationalite = ?, langue_parlee = ?,
                        date_arrivee = ?, date_naissance = ?, profession = ?, notes = ?";
                $params = [$email, $nom, $prenom, $telephone, $adresse, $ville, $code_postal,
                          $pays_origine, $nationalite, $langue_parlee,
                          $date_arrivee ?: null, $date_naissance ?: null, $profession, $notes];

                if ($photoFilename) {
                    $sql .= ", photo = ?";
                    $params[] = $photoFilename;
                }
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $editId;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                setFlashMessage('success', 'Membre modifié avec succès.');
            } else {
                // Création
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, nom, prenom, role, telephone, adresse,
                    ville, code_postal, pays_origine, nationalite, langue_parlee, date_arrivee, date_naissance,
                    profession, photo, notes, actif, created_at)
                    VALUES (?, ?, ?, ?, 'membre', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$email, $hash, $nom, $prenom, $telephone, $adresse, $ville, $code_postal,
                    $pays_origine, $nationalite, $langue_parlee, $date_arrivee ?: null, $date_naissance ?: null,
                    $profession, $photoFilename, $notes]);
                setFlashMessage('success', 'Membre créé avec succès.');
            }
            header('Location: membres.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'enregistrement.';
        }
    }
}

// Récupérer le membre à éditer
$editMember = null;
if ($action === 'edit' && $memberId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'membre'");
    $stmt->execute([$memberId]);
    $editMember = $stmt->fetch();
}

// Voir le détail d'un membre
$viewMember = null;
$memberCotisations = [];
$memberInscriptions = [];
$memberNotes = [];

if ($action === 'view' && $memberId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$memberId]);
    $viewMember = $stmt->fetch();

    if ($viewMember) {
        // Cotisations
        $stmt = $pdo->prepare("SELECT c.*, ct.nom as type_nom FROM cotisations c
            JOIN cotisation_types ct ON c.cotisation_type_id = ct.id
            WHERE c.user_id = ? ORDER BY c.date_fin DESC");
        $stmt->execute([$memberId]);
        $memberCotisations = $stmt->fetchAll();

        // Inscriptions aux événements
        $stmt = $pdo->prepare("SELECT i.*, e.titre, e.date_debut, e.type FROM inscriptions i
            JOIN evenements e ON i.evenement_id = e.id
            WHERE i.user_id = ? ORDER BY e.date_debut DESC");
        $stmt->execute([$memberId]);
        $memberInscriptions = $stmt->fetchAll();

        // Notes
        $stmt = $pdo->prepare("SELECT n.*, u.nom as auteur_nom, u.prenom as auteur_prenom
            FROM membre_notes n JOIN users u ON n.auteur_id = u.id
            WHERE n.user_id = ? ORDER BY n.created_at DESC");
        $stmt->execute([$memberId]);
        $memberNotes = $stmt->fetchAll();
    }
}

// Ajouter une note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $noteUserId = (int)$_POST['note_user_id'];
    $noteContent = trim($_POST['note_content'] ?? '');

    if (!empty($noteContent)) {
        $stmt = $pdo->prepare("INSERT INTO membre_notes (user_id, auteur_id, contenu) VALUES (?, ?, ?)");
        $stmt->execute([$noteUserId, $currentUser['id'], $noteContent]);
        setFlashMessage('success', 'Note ajoutée.');
        header("Location: membres.php?action=view&id=$noteUserId");
        exit;
    }
}

// Filtres
$search = trim($_GET['search'] ?? '');
$filterCotisation = $_GET['cotisation'] ?? '';

// Liste des membres
$sql = "SELECT u.*,
        (SELECT c.date_fin FROM cotisations c WHERE c.user_id = u.id AND c.statut = 'paye' ORDER BY c.date_fin DESC LIMIT 1) as cotisation_fin
        FROM users u WHERE u.role = 'membre'";
$params = [];

if ($search) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY u.nom, u.prenom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$membres = $stmt->fetchAll();

// Filtrer par statut cotisation
if ($filterCotisation) {
    $membres = array_filter($membres, function($m) use ($filterCotisation) {
        $status = getCotisationStatus($m['cotisation_fin']);
        return $status === $filterCotisation;
    });
}

function getCotisationStatus($dateFin) {
    if (!$dateFin) return 'aucune';
    $fin = strtotime($dateFin);
    $now = time();
    $dans30j = strtotime('+30 days');

    if ($fin < $now) return 'expiree';
    if ($fin < $dans30j) return 'expire_bientot';
    return 'a_jour';
}

function getCotisationBadge($dateFin) {
    $status = getCotisationStatus($dateFin);
    switch ($status) {
        case 'a_jour': return '<span class="badge badge-success">À jour</span>';
        case 'expire_bientot': return '<span class="badge" style="background:rgba(255,152,0,0.15);color:#e65100;">Expire bientôt</span>';
        case 'expiree': return '<span class="badge badge-danger">Expirée</span>';
        default: return '<span class="badge" style="background:#eee;color:#666;">Aucune</span>';
    }
}
?>

<?php if ($action === 'view' && $viewMember): ?>
<!-- Vue détail membre -->
<div class="card">
    <div class="card-header">
        <h3>
            <?php if ($viewMember['photo']): ?>
                <img src="<?php echo getBaseUrl(); ?>/assets/uploads/<?php echo e($viewMember['photo']); ?>"
                     style="width:40px;height:40px;border-radius:50%;object-fit:cover;margin-right:10px;vertical-align:middle;">
            <?php endif; ?>
            <?php echo e($viewMember['prenom'] . ' ' . $viewMember['nom']); ?>
        </h3>
        <div class="btn-group">
            <a href="?action=edit&id=<?php echo $viewMember['id']; ?>" class="btn btn-sm btn-primary">Modifier</a>
            <a href="membres.php" class="btn btn-sm btn-secondary">Retour</a>
        </div>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
            <div>
                <h4 style="margin-bottom:15px;color:var(--primary);">Informations personnelles</h4>
                <table class="info-table">
                    <tr><td>Email</td><td><?php echo e($viewMember['email']); ?></td></tr>
                    <tr><td>Téléphone</td><td><?php echo e($viewMember['telephone'] ?: '-'); ?></td></tr>
                    <tr><td>Adresse</td><td><?php echo e($viewMember['adresse'] ?: '-'); ?></td></tr>
                    <tr><td>Ville</td><td><?php echo e(($viewMember['code_postal'] ? $viewMember['code_postal'].' ' : '') . ($viewMember['ville'] ?: '-')); ?></td></tr>
                    <tr><td>Date de naissance</td><td><?php echo $viewMember['date_naissance'] ? formatDate($viewMember['date_naissance']) : '-'; ?></td></tr>
                    <tr><td>Profession</td><td><?php echo e($viewMember['profession'] ?: '-'); ?></td></tr>
                </table>
            </div>
            <div>
                <h4 style="margin-bottom:15px;color:var(--primary);">Profil francophone</h4>
                <table class="info-table">
                    <tr><td>Pays d'origine</td><td><?php echo e($viewMember['pays_origine'] ?: '-'); ?></td></tr>
                    <tr><td>Nationalité</td><td><?php echo e($viewMember['nationalite'] ?: '-'); ?></td></tr>
                    <tr><td>Langues parlées</td><td><?php echo e($viewMember['langue_parlee'] ?: '-'); ?></td></tr>
                    <tr><td>Date d'arrivée</td><td><?php echo $viewMember['date_arrivee'] ? formatDate($viewMember['date_arrivee']) : '-'; ?></td></tr>
                    <tr><td>Membre depuis</td><td><?php echo formatDate($viewMember['created_at']); ?></td></tr>
                    <tr><td>Cotisation</td><td><?php echo getCotisationBadge($viewMember['cotisation_fin'] ?? null); ?></td></tr>
                </table>
            </div>
        </div>

        <?php if ($viewMember['notes']): ?>
        <div style="margin-top:20px;padding:15px;background:#f5f5f5;border-radius:8px;">
            <strong>Notes :</strong> <?php echo nl2br(e($viewMember['notes'])); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cotisations -->
<div class="card">
    <div class="card-header">
        <h3>Historique des cotisations</h3>
        <a href="cotisations.php?action=add&user_id=<?php echo $viewMember['id']; ?>" class="btn btn-sm btn-primary">+ Ajouter</a>
    </div>
    <div class="card-body">
        <?php if (empty($memberCotisations)): ?>
            <p style="color:#666;">Aucune cotisation enregistrée.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Type</th><th>Période</th><th>Montant</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($memberCotisations as $cot): ?>
                    <tr>
                        <td><?php echo e($cot['type_nom']); ?></td>
                        <td><?php echo formatDate($cot['date_debut']); ?> - <?php echo formatDate($cot['date_fin']); ?></td>
                        <td><?php echo number_format($cot['montant'], 2); ?> €</td>
                        <td>
                            <?php if ($cot['statut'] === 'paye'): ?>
                                <span class="badge badge-success">Payé</span>
                            <?php elseif ($cot['statut'] === 'en_attente'): ?>
                                <span class="badge" style="background:rgba(255,152,0,0.15);color:#e65100;">En attente</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Annulé</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Participations aux événements -->
<div class="card">
    <div class="card-header">
        <h3>Participations aux événements</h3>
    </div>
    <div class="card-body">
        <?php if (empty($memberInscriptions)): ?>
            <p style="color:#666;">Aucune participation enregistrée.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Événement</th><th>Date</th><th>Places</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($memberInscriptions as $insc): ?>
                    <tr>
                        <td>
                            <a href="evenements.php?action=view&id=<?php echo $insc['evenement_id']; ?>">
                                <?php echo e($insc['titre']); ?>
                            </a>
                        </td>
                        <td><?php echo formatDateTime($insc['date_debut']); ?></td>
                        <td><?php echo $insc['nombre_places']; ?></td>
                        <td>
                            <?php
                            $badges = [
                                'confirme' => '<span class="badge badge-success">Confirmé</span>',
                                'en_attente' => '<span class="badge" style="background:rgba(255,152,0,0.15);color:#e65100;">En attente</span>',
                                'present' => '<span class="badge badge-info">Présent</span>',
                                'annule' => '<span class="badge badge-danger">Annulé</span>'
                            ];
                            echo $badges[$insc['statut']] ?? $insc['statut'];
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Notes et commentaires -->
<div class="card">
    <div class="card-header">
        <h3>Notes et commentaires</h3>
    </div>
    <div class="card-body">
        <form method="POST" style="margin-bottom:20px;">
            <input type="hidden" name="add_note" value="1">
            <input type="hidden" name="note_user_id" value="<?php echo $viewMember['id']; ?>">
            <div class="form-group" style="margin-bottom:10px;">
                <textarea name="note_content" placeholder="Ajouter une note..." rows="2" required></textarea>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Ajouter la note</button>
        </form>

        <?php if (empty($memberNotes)): ?>
            <p style="color:#666;">Aucune note.</p>
        <?php else: ?>
            <?php foreach ($memberNotes as $note): ?>
                <div style="padding:10px;background:#f9f9f9;border-radius:5px;margin-bottom:10px;">
                    <div style="font-size:12px;color:#666;margin-bottom:5px;">
                        <?php echo e($note['auteur_prenom'] . ' ' . $note['auteur_nom']); ?> -
                        <?php echo formatDateTime($note['created_at']); ?>
                    </div>
                    <div><?php echo nl2br(e($note['contenu'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<!-- Modal Ajouter/Modifier -->
<div class="modal-overlay <?php echo ($action === 'add' || $editMember) ? 'active' : ''; ?>" id="memberModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3><?php echo $editMember ? 'Modifier le membre' : 'Nouveau membre'; ?></h3>
            <button class="modal-close" onclick="window.location.href='membres.php'">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <input type="hidden" name="edit_id" value="<?php echo $editMember ? $editMember['id'] : 0; ?>">

                <div class="section-title" style="margin-top:0;">Identité</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" value="<?php echo e($editMember['nom'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" value="<?php echo e($editMember['prenom'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo e($editMember['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone" value="<?php echo e($editMember['telephone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Photo</label>
                    <input type="file" name="photo" accept="image/*">
                </div>

                <div class="section-title">Coordonnées</div>
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" name="adresse" value="<?php echo e($editMember['adresse'] ?? ''); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Code postal</label>
                        <input type="text" name="code_postal" value="<?php echo e($editMember['code_postal'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Ville</label>
                        <input type="text" name="ville" value="<?php echo e($editMember['ville'] ?? ''); ?>">
                    </div>
                </div>

                <div class="section-title">Profil francophone</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Pays d'origine</label>
                        <input type="text" name="pays_origine" value="<?php echo e($editMember['pays_origine'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nationalité</label>
                        <input type="text" name="nationalite" value="<?php echo e($editMember['nationalite'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Langues parlées</label>
                        <input type="text" name="langue_parlee" value="<?php echo e($editMember['langue_parlee'] ?? ''); ?>" placeholder="Français, Anglais...">
                    </div>
                    <div class="form-group">
                        <label>Date d'arrivée</label>
                        <input type="date" name="date_arrivee" value="<?php echo e($editMember['date_arrivee'] ?? ''); ?>">
                    </div>
                </div>

                <div class="section-title">Informations complémentaires</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date de naissance</label>
                        <input type="date" name="date_naissance" value="<?php echo e($editMember['date_naissance'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Profession</label>
                        <input type="text" name="profession" value="<?php echo e($editMember['profession'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"><?php echo e($editMember['notes'] ?? ''); ?></textarea>
                </div>

                <div class="section-title">Accès</div>
                <div class="form-group">
                    <label>Mot de passe <?php echo $editMember ? '' : '*'; ?></label>
                    <input type="password" name="password" <?php echo $editMember ? '' : 'required'; ?>>
                    <?php if ($editMember): ?>
                        <p class="form-hint">Laissez vide pour conserver le mot de passe actuel.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <a href="membres.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary"><?php echo $editMember ? 'Modifier' : 'Créer'; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des membres -->
<div class="card">
    <div class="card-header">
        <h3>Liste des membres (<?php echo count($membres); ?>)</h3>
        <a href="?action=add" class="btn btn-primary btn-sm">+ Nouveau membre</a>
    </div>
    <div class="card-body">
        <!-- Filtres -->
        <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
            <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Rechercher..." style="flex:1;min-width:200px;">
            <select name="cotisation" style="width:auto;">
                <option value="">Toutes cotisations</option>
                <option value="a_jour" <?php echo $filterCotisation === 'a_jour' ? 'selected' : ''; ?>>À jour</option>
                <option value="expire_bientot" <?php echo $filterCotisation === 'expire_bientot' ? 'selected' : ''; ?>>Expire bientôt</option>
                <option value="expiree" <?php echo $filterCotisation === 'expiree' ? 'selected' : ''; ?>>Expirée</option>
                <option value="aucune" <?php echo $filterCotisation === 'aucune' ? 'selected' : ''; ?>>Aucune</option>
            </select>
            <button type="submit" class="btn btn-sm btn-secondary">Filtrer</button>
            <?php if ($search || $filterCotisation): ?>
                <a href="membres.php" class="btn btn-sm" style="background:#eee;">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <?php if (empty($membres)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#9787;</div>
                <h3>Aucun membre</h3>
                <p>Créez votre premier membre en cliquant sur le bouton ci-dessus.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>Contact</th>
                            <th>Origine</th>
                            <th>Cotisation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membres as $membre): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <?php if ($membre['photo']): ?>
                                            <img src="<?php echo getBaseUrl(); ?>/assets/uploads/<?php echo e($membre['photo']); ?>"
                                                 style="width:35px;height:35px;border-radius:50%;object-fit:cover;">
                                        <?php else: ?>
                                            <div style="width:35px;height:35px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;">
                                                <?php echo e(substr($membre['prenom'] ?: $membre['nom'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo e($membre['prenom'] . ' ' . $membre['nom']); ?></strong>
                                            <?php if ($membre['profession']): ?>
                                                <div style="font-size:12px;color:#666;"><?php echo e($membre['profession']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:13px;">
                                        <?php echo e($membre['email']); ?>
                                        <?php if ($membre['telephone']): ?>
                                            <br><span style="color:#666;"><?php echo e($membre['telephone']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($membre['pays_origine']): ?>
                                        <?php echo e($membre['pays_origine']); ?>
                                    <?php else: ?>
                                        <span style="color:#999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getCotisationBadge($membre['cotisation_fin']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?action=view&id=<?php echo $membre['id']; ?>" class="btn btn-sm btn-primary">Voir</a>
                                        <a href="?action=edit&id=<?php echo $membre['id']; ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                        <a href="?action=delete&id=<?php echo $membre['id']; ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirmDelete('Supprimer ce membre ?');">Supprimer</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<style>
.info-table { width: 100%; }
.info-table td { padding: 8px 0; border-bottom: 1px solid #eee; }
.info-table td:first-child { font-weight: 500; color: #666; width: 140px; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

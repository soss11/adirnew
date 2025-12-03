<?php
/**
 * Mini CRM - Gestion des réunions et comptes-rendus (AG, CA, etc.)
 */

require_once __DIR__ . '/../includes/auth.php';
requireGestionnaire();

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'type_reunion' => $_POST['type_reunion'],
            'titre' => $_POST['titre'],
            'date_reunion' => $_POST['date_reunion'],
            'lieu' => $_POST['lieu'] ?? '',
            'ordre_du_jour' => $_POST['ordre_du_jour'] ?? '',
            'quorum_requis' => intval($_POST['quorum_requis'] ?? 0)
        ];

        if ($postAction === 'create') {
            $data['cree_par'] = getCurrentUserId();
            $stmt = $pdo->prepare("INSERT INTO reunions (type_reunion, titre, date_reunion, lieu, ordre_du_jour, quorum_requis, cree_par) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array_values($data));
            $reunionId = $pdo->lastInsertId();
            setFlashMessage('success', 'Réunion créée avec succès.');
            header('Location: reunions.php?action=edit&id=' . $reunionId);
            exit;
        } else {
            $reunionId = intval($_POST['reunion_id']);
            $stmt = $pdo->prepare("UPDATE reunions SET type_reunion=?, titre=?, date_reunion=?, lieu=?, ordre_du_jour=?, quorum_requis=? WHERE id=?");
            $stmt->execute([...array_values($data), $reunionId]);
            setFlashMessage('success', 'Réunion mise à jour.');
            header('Location: reunions.php?action=edit&id=' . $reunionId);
            exit;
        }
    }

    if ($postAction === 'save_compte_rendu') {
        $reunionId = intval($_POST['reunion_id']);
        $stmt = $pdo->prepare("UPDATE reunions SET compte_rendu=?, decisions=?, statut=? WHERE id=?");
        $stmt->execute([
            $_POST['compte_rendu'],
            $_POST['decisions'],
            $_POST['statut'],
            $reunionId
        ]);
        setFlashMessage('success', 'Compte-rendu enregistré.');
        header('Location: reunions.php?action=edit&id=' . $reunionId);
        exit;
    }

    if ($postAction === 'add_participant') {
        $reunionId = intval($_POST['reunion_id']);
        $userId = intval($_POST['user_id']);
        $role = $_POST['role_reunion'];

        try {
            $stmt = $pdo->prepare("INSERT INTO reunion_participants (reunion_id, user_id, role_reunion) VALUES (?, ?, ?)");
            $stmt->execute([$reunionId, $userId, $role]);
            setFlashMessage('success', 'Participant ajouté.');
        } catch (PDOException $e) {
            setFlashMessage('error', 'Ce participant est déjà dans la liste.');
        }
        header('Location: reunions.php?action=edit&id=' . $reunionId);
        exit;
    }

    if ($postAction === 'update_presence') {
        $reunionId = intval($_POST['reunion_id']);
        $participantId = intval($_POST['participant_id']);
        $presence = $_POST['presence'];

        $stmt = $pdo->prepare("UPDATE reunion_participants SET presence=? WHERE id=?");
        $stmt->execute([$presence, $participantId]);
        setFlashMessage('success', 'Présence mise à jour.');
        header('Location: reunions.php?action=edit&id=' . $reunionId);
        exit;
    }

    if ($postAction === 'remove_participant') {
        $reunionId = intval($_POST['reunion_id']);
        $participantId = intval($_POST['participant_id']);

        $stmt = $pdo->prepare("DELETE FROM reunion_participants WHERE id=?");
        $stmt->execute([$participantId]);
        setFlashMessage('success', 'Participant retiré.');
        header('Location: reunions.php?action=edit&id=' . $reunionId);
        exit;
    }

    if ($postAction === 'add_vote') {
        $reunionId = intval($_POST['reunion_id']);
        $stmt = $pdo->prepare("INSERT INTO reunion_votes (reunion_id, sujet, description, type_vote) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $reunionId,
            $_POST['sujet'],
            $_POST['description'] ?? '',
            $_POST['type_vote']
        ]);
        setFlashMessage('success', 'Vote ajouté.');
        header('Location: reunions.php?action=edit&id=' . $reunionId);
        exit;
    }

    if ($postAction === 'update_vote') {
        $reunionId = intval($_POST['reunion_id']);
        $voteId = intval($_POST['vote_id']);
        $stmt = $pdo->prepare("UPDATE reunion_votes SET votes_pour=?, votes_contre=?, abstentions=?, resultat=? WHERE id=?");
        $stmt->execute([
            intval($_POST['votes_pour']),
            intval($_POST['votes_contre']),
            intval($_POST['abstentions']),
            $_POST['resultat'],
            $voteId
        ]);
        setFlashMessage('success', 'Vote mis à jour.');
        header('Location: reunions.php?action=edit&id=' . $reunionId);
        exit;
    }

    if ($postAction === 'sign') {
        $reunionId = intval($_POST['reunion_id']);
        $signatureData = $_POST['signature_data'];
        $role = $_POST['role_signataire'];

        try {
            $stmt = $pdo->prepare("INSERT INTO reunion_signatures (reunion_id, user_id, role_signataire, signature_data, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $reunionId,
                getCurrentUserId(),
                $role,
                $signatureData,
                $_SERVER['REMOTE_ADDR']
            ]);
            setFlashMessage('success', 'Signature enregistrée.');
        } catch (PDOException $e) {
            setFlashMessage('error', 'Vous avez déjà signé ce document.');
        }
        header('Location: reunions.php?action=edit&id=' . $reunionId);
        exit;
    }

    if ($postAction === 'validate') {
        $reunionId = intval($_POST['reunion_id']);
        $stmt = $pdo->prepare("UPDATE reunions SET valide_par=?, valide_at=NOW(), statut='terminee' WHERE id=?");
        $stmt->execute([getCurrentUserId(), $reunionId]);
        setFlashMessage('success', 'Compte-rendu validé officiellement.');
        header('Location: reunions.php?action=edit&id=' . $reunionId);
        exit;
    }

    if ($postAction === 'delete') {
        $reunionId = intval($_POST['reunion_id']);
        $stmt = $pdo->prepare("DELETE FROM reunions WHERE id=?");
        $stmt->execute([$reunionId]);
        setFlashMessage('success', 'Réunion supprimée.');
        header('Location: reunions.php');
        exit;
    }
}

$pageTitle = 'Réunions & Comptes-rendus';
require_once __DIR__ . '/../includes/header.php';

// Types de réunion
$typesReunion = [
    'ag' => 'Assemblée Générale',
    'age' => 'AG Extraordinaire',
    'ca' => 'Conseil d\'Administration',
    'bureau' => 'Réunion de Bureau',
    'commission' => 'Commission',
    'autre' => 'Autre'
];

$rolesReunion = [
    'president' => 'Président(e)',
    'secretaire' => 'Secrétaire',
    'tresorier' => 'Trésorier(ère)',
    'membre' => 'Membre',
    'invite' => 'Invité(e)'
];

$presenceOptions = [
    'present' => 'Présent',
    'absent' => 'Absent',
    'excuse' => 'Excusé',
    'procuration' => 'Procuration'
];
?>

<?php if ($action === 'list'): ?>
    <?php
    // Liste des réunions
    $stmt = $pdo->query("
        SELECT r.*, u.prenom, u.nom,
            (SELECT COUNT(*) FROM reunion_participants WHERE reunion_id = r.id AND presence = 'present') as nb_presents,
            (SELECT COUNT(*) FROM reunion_signatures WHERE reunion_id = r.id) as nb_signatures
        FROM reunions r
        LEFT JOIN users u ON r.cree_par = u.id
        ORDER BY r.date_reunion DESC
    ");
    $reunions = $stmt->fetchAll();
    ?>

    <div class="card">
        <div class="card-header">
            <h3>Réunions & Comptes-rendus</h3>
            <a href="?action=new" class="btn btn-primary">+ Nouvelle réunion</a>
        </div>
        <div class="card-body">
            <?php if (empty($reunions)): ?>
                <p class="text-muted">Aucune réunion enregistrée.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Titre</th>
                            <th>Lieu</th>
                            <th>Présents</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reunions as $r): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($r['date_reunion'])); ?></td>
                                <td>
                                    <span class="badge badge-info"><?php echo e($typesReunion[$r['type_reunion']] ?? $r['type_reunion']); ?></span>
                                </td>
                                <td><strong><?php echo e($r['titre']); ?></strong></td>
                                <td><?php echo e($r['lieu']); ?></td>
                                <td>
                                    <?php echo $r['nb_presents']; ?> présent(s)
                                    <?php if ($r['nb_signatures'] > 0): ?>
                                        <br><small class="text-success"><?php echo $r['nb_signatures']; ?> signature(s)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statutColors = [
                                        'planifiee' => 'warning',
                                        'en_cours' => 'info',
                                        'terminee' => 'success',
                                        'annulee' => 'danger'
                                    ];
                                    $statutLabels = [
                                        'planifiee' => 'Planifiée',
                                        'en_cours' => 'En cours',
                                        'terminee' => 'Terminée',
                                        'annulee' => 'Annulée'
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $statutColors[$r['statut']]; ?>">
                                        <?php echo $statutLabels[$r['statut']]; ?>
                                    </span>
                                    <?php if ($r['valide_par']): ?>
                                        <br><small class="text-success">Validé</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?action=edit&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-secondary">Gérer</a>
                                    <a href="?action=print&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-info" target="_blank">PDF</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'new'): ?>
    <div class="card">
        <div class="card-header">
            <h3>Nouvelle réunion</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Type de réunion *</label>
                        <select name="type_reunion" class="form-control" required>
                            <?php foreach ($typesReunion as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Date et heure *</label>
                        <input type="datetime-local" name="date_reunion" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="titre" class="form-control" required placeholder="Ex: AG Ordinaire 2024">
                </div>

                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Lieu</label>
                        <input type="text" name="lieu" class="form-control" placeholder="Adresse de la réunion">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Quorum requis</label>
                        <input type="number" name="quorum_requis" class="form-control" value="0" min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label>Ordre du jour</label>
                    <textarea name="ordre_du_jour" class="form-control" rows="6" placeholder="1. Approbation du PV de la dernière AG&#10;2. Rapport moral&#10;3. Rapport financier&#10;..."></textarea>
                </div>

                <div class="form-actions">
                    <a href="reunions.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">Créer la réunion</button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'edit' && $id): ?>
    <?php
    // Récupérer la réunion
    $stmt = $pdo->prepare("SELECT r.*, u.prenom as createur_prenom, u.nom as createur_nom, v.prenom as valideur_prenom, v.nom as valideur_nom
        FROM reunions r
        LEFT JOIN users u ON r.cree_par = u.id
        LEFT JOIN users v ON r.valide_par = v.id
        WHERE r.id = ?");
    $stmt->execute([$id]);
    $reunion = $stmt->fetch();

    if (!$reunion) {
        setFlashMessage('error', 'Réunion non trouvée.');
        header('Location: reunions.php');
        exit;
    }

    // Participants
    $stmt = $pdo->prepare("
        SELECT rp.*, u.prenom, u.nom, u.email
        FROM reunion_participants rp
        JOIN users u ON rp.user_id = u.id
        WHERE rp.reunion_id = ?
        ORDER BY FIELD(rp.role_reunion, 'president', 'secretaire', 'tresorier', 'membre', 'invite')
    ");
    $stmt->execute([$id]);
    $participants = $stmt->fetchAll();

    // Votes
    $stmt = $pdo->prepare("SELECT * FROM reunion_votes WHERE reunion_id = ? ORDER BY ordre, id");
    $stmt->execute([$id]);
    $votes = $stmt->fetchAll();

    // Signatures
    $stmt = $pdo->prepare("
        SELECT rs.*, u.prenom, u.nom
        FROM reunion_signatures rs
        JOIN users u ON rs.user_id = u.id
        WHERE rs.reunion_id = ?
    ");
    $stmt->execute([$id]);
    $signatures = $stmt->fetchAll();

    // Membres pour ajouter
    $stmt = $pdo->query("SELECT id, prenom, nom, email FROM users WHERE actif = 1 ORDER BY nom, prenom");
    $membres = $stmt->fetchAll();

    // Stats présence
    $nbPresents = count(array_filter($participants, fn($p) => $p['presence'] === 'present'));
    $nbProcurations = count(array_filter($participants, fn($p) => $p['presence'] === 'procuration'));
    $quorumAtteint = ($reunion['quorum_requis'] == 0) || (($nbPresents + $nbProcurations) >= $reunion['quorum_requis']);
    ?>

    <div class="card mb-4">
        <div class="card-header">
            <h3>
                <span class="badge badge-info"><?php echo e($typesReunion[$reunion['type_reunion']]); ?></span>
                <?php echo e($reunion['titre']); ?>
            </h3>
            <div>
                <a href="?action=print&id=<?php echo $id; ?>" class="btn btn-info" target="_blank">Imprimer PDF</a>
                <a href="reunions.php" class="btn btn-secondary">Retour</a>
            </div>
        </div>
        <div class="card-body">
            <!-- Infos générales -->
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Type</label>
                        <select name="type_reunion" class="form-control">
                            <?php foreach ($typesReunion as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $reunion['type_reunion'] === $key ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Date et heure</label>
                        <input type="datetime-local" name="date_reunion" class="form-control"
                               value="<?php echo date('Y-m-d\TH:i', strtotime($reunion['date_reunion'])); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Quorum requis</label>
                        <input type="number" name="quorum_requis" class="form-control" value="<?php echo $reunion['quorum_requis']; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Titre</label>
                        <input type="text" name="titre" class="form-control" value="<?php echo e($reunion['titre']); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Lieu</label>
                        <input type="text" name="lieu" class="form-control" value="<?php echo e($reunion['lieu']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Ordre du jour</label>
                    <textarea name="ordre_du_jour" class="form-control" rows="4"><?php echo e($reunion['ordre_du_jour']); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </form>
        </div>
    </div>

    <!-- Participants et présences -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Participants (<?php echo count($participants); ?>)</h3>
            <?php if ($reunion['quorum_requis'] > 0): ?>
                <span class="badge badge-<?php echo $quorumAtteint ? 'success' : 'danger'; ?>">
                    Quorum: <?php echo $nbPresents + $nbProcurations; ?>/<?php echo $reunion['quorum_requis']; ?>
                    <?php echo $quorumAtteint ? '(atteint)' : '(non atteint)'; ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <!-- Ajouter un participant -->
            <form method="POST" class="form-inline mb-4">
                <input type="hidden" name="action" value="add_participant">
                <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">

                <select name="user_id" class="form-control mr-2" required>
                    <option value="">-- Sélectionner un membre --</option>
                    <?php foreach ($membres as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo e($m['nom'] . ' ' . $m['prenom']); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="role_reunion" class="form-control mr-2">
                    <?php foreach ($rolesReunion as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo e($label); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary">Ajouter</button>
            </form>

            <?php if (empty($participants)): ?>
                <p class="text-muted">Aucun participant enregistré.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Rôle</th>
                            <th>Présence</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $p): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($p['nom'] . ' ' . $p['prenom']); ?></strong>
                                    <br><small><?php echo e($p['email']); ?></small>
                                </td>
                                <td><?php echo e($rolesReunion[$p['role_reunion']] ?? $p['role_reunion']); ?></td>
                                <td>
                                    <form method="POST" class="form-inline">
                                        <input type="hidden" name="action" value="update_presence">
                                        <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="participant_id" value="<?php echo $p['id']; ?>">
                                        <select name="presence" class="form-control form-control-sm" onchange="this.form.submit()">
                                            <?php foreach ($presenceOptions as $key => $label): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $p['presence'] === $key ? 'selected' : ''; ?>>
                                                    <?php echo e($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="remove_participant">
                                        <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">
                                        <input type="hidden" name="participant_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Retirer ce participant ?')">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Votes en réunion -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Votes & Décisions (<?php echo count($votes); ?>)</h3>
        </div>
        <div class="card-body">
            <!-- Ajouter un vote -->
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="add_vote">
                <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <input type="text" name="sujet" class="form-control" placeholder="Sujet du vote" required>
                    </div>
                    <div class="form-group col-md-4">
                        <select name="type_vote" class="form-control">
                            <option value="main_levee">Main levée</option>
                            <option value="bulletin">Bulletin secret</option>
                            <option value="unanime">Unanime</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">Ajouter</button>
                    </div>
                </div>
            </form>

            <?php if (!empty($votes)): ?>
                <?php foreach ($votes as $vote): ?>
                    <div class="card mb-2">
                        <div class="card-body">
                            <h5><?php echo e($vote['sujet']); ?></h5>
                            <form method="POST" class="form-inline">
                                <input type="hidden" name="action" value="update_vote">
                                <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="vote_id" value="<?php echo $vote['id']; ?>">

                                <div class="mr-3">
                                    <label class="mr-1">Pour:</label>
                                    <input type="number" name="votes_pour" class="form-control form-control-sm" style="width:60px" value="<?php echo $vote['votes_pour']; ?>" min="0">
                                </div>
                                <div class="mr-3">
                                    <label class="mr-1">Contre:</label>
                                    <input type="number" name="votes_contre" class="form-control form-control-sm" style="width:60px" value="<?php echo $vote['votes_contre']; ?>" min="0">
                                </div>
                                <div class="mr-3">
                                    <label class="mr-1">Abst.:</label>
                                    <input type="number" name="abstentions" class="form-control form-control-sm" style="width:60px" value="<?php echo $vote['abstentions']; ?>" min="0">
                                </div>
                                <div class="mr-3">
                                    <select name="resultat" class="form-control form-control-sm">
                                        <option value="">-- Résultat --</option>
                                        <option value="adopte" <?php echo $vote['resultat'] === 'adopte' ? 'selected' : ''; ?>>Adopté</option>
                                        <option value="rejete" <?php echo $vote['resultat'] === 'rejete' ? 'selected' : ''; ?>>Rejeté</option>
                                        <option value="reporte" <?php echo $vote['resultat'] === 'reporte' ? 'selected' : ''; ?>>Reporté</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Compte-rendu -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Compte-rendu</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_compte_rendu">
                <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">

                <div class="form-group">
                    <label>Compte-rendu détaillé</label>
                    <textarea name="compte_rendu" class="form-control" rows="10"><?php echo e($reunion['compte_rendu']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Décisions prises</label>
                    <textarea name="decisions" class="form-control" rows="5" placeholder="Liste des décisions importantes..."><?php echo e($reunion['decisions']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Statut</label>
                    <select name="statut" class="form-control" style="max-width:200px">
                        <option value="planifiee" <?php echo $reunion['statut'] === 'planifiee' ? 'selected' : ''; ?>>Planifiée</option>
                        <option value="en_cours" <?php echo $reunion['statut'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                        <option value="terminee" <?php echo $reunion['statut'] === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                        <option value="annulee" <?php echo $reunion['statut'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer le compte-rendu</button>
            </form>
        </div>
    </div>

    <!-- Signatures -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Signatures du PV (<?php echo count($signatures); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($signatures)): ?>
                <div class="row mb-4">
                    <?php foreach ($signatures as $sig): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <p><strong><?php echo e($sig['nom'] . ' ' . $sig['prenom']); ?></strong></p>
                                    <p class="text-muted"><?php echo e($sig['role_signataire']); ?></p>
                                    <?php if ($sig['signature_data']): ?>
                                        <img src="<?php echo e($sig['signature_data']); ?>" alt="Signature" style="max-width:150px; border:1px solid #ddd;">
                                    <?php endif; ?>
                                    <p class="small text-muted mt-2">
                                        Signé le <?php echo date('d/m/Y H:i', strtotime($sig['signe_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
            $alreadySigned = false;
            foreach ($signatures as $sig) {
                if ($sig['user_id'] == getCurrentUserId()) {
                    $alreadySigned = true;
                    break;
                }
            }
            ?>

            <?php if (!$alreadySigned): ?>
                <div class="card bg-light">
                    <div class="card-body">
                        <h5>Signer le procès-verbal</h5>
                        <form method="POST">
                            <input type="hidden" name="action" value="sign">
                            <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">
                            <input type="hidden" name="signature_data" id="signature_data">

                            <div class="form-group">
                                <label>Votre rôle</label>
                                <input type="text" name="role_signataire" class="form-control" style="max-width:300px" placeholder="Ex: Président, Secrétaire...">
                            </div>

                            <div class="form-group">
                                <label>Signature</label>
                                <div style="border:1px solid #ccc; background:#fff;">
                                    <canvas id="signature-pad" width="400" height="150" style="cursor:crosshair;"></canvas>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="clearSignature()">Effacer</button>
                            </div>

                            <button type="submit" class="btn btn-success" onclick="return saveSignature()">Signer le document</button>
                        </form>
                    </div>
                </div>

                <script>
                const canvas = document.getElementById('signature-pad');
                const ctx = canvas.getContext('2d');
                let isDrawing = false;
                let lastX = 0;
                let lastY = 0;

                ctx.strokeStyle = '#000';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';

                canvas.addEventListener('mousedown', (e) => {
                    isDrawing = true;
                    [lastX, lastY] = [e.offsetX, e.offsetY];
                });

                canvas.addEventListener('mousemove', (e) => {
                    if (!isDrawing) return;
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(e.offsetX, e.offsetY);
                    ctx.stroke();
                    [lastX, lastY] = [e.offsetX, e.offsetY];
                });

                canvas.addEventListener('mouseup', () => isDrawing = false);
                canvas.addEventListener('mouseout', () => isDrawing = false);

                function clearSignature() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                }

                function saveSignature() {
                    const dataURL = canvas.toDataURL('image/png');
                    document.getElementById('signature_data').value = dataURL;
                    return true;
                }
                </script>
            <?php else: ?>
                <p class="text-success">Vous avez déjà signé ce document.</p>
            <?php endif; ?>

            <?php if (isAdmin() && !$reunion['valide_par']): ?>
                <hr>
                <form method="POST" class="mt-3">
                    <input type="hidden" name="action" value="validate">
                    <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Valider officiellement ce compte-rendu ?')">
                        Valider officiellement le PV
                    </button>
                </form>
            <?php elseif ($reunion['valide_par']): ?>
                <div class="alert alert-success mt-3">
                    <strong>Document validé</strong> par <?php echo e($reunion['valideur_prenom'] . ' ' . $reunion['valideur_nom']); ?>
                    le <?php echo date('d/m/Y à H:i', strtotime($reunion['valide_at'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Suppression -->
    <?php if (isAdmin()): ?>
        <div class="card border-danger">
            <div class="card-body">
                <form method="POST" onsubmit="return confirm('Supprimer définitivement cette réunion ?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="reunion_id" value="<?php echo $id; ?>">
                    <button type="submit" class="btn btn-danger">Supprimer cette réunion</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'print' && $id): ?>
    <?php
    // Génération PDF du PV
    $stmt = $pdo->prepare("SELECT r.*, u.prenom as createur_prenom, u.nom as createur_nom FROM reunions r LEFT JOIN users u ON r.cree_par = u.id WHERE r.id = ?");
    $stmt->execute([$id]);
    $reunion = $stmt->fetch();

    if (!$reunion) {
        die('Réunion non trouvée');
    }

    // Participants
    $stmt = $pdo->prepare("SELECT rp.*, u.prenom, u.nom FROM reunion_participants rp JOIN users u ON rp.user_id = u.id WHERE rp.reunion_id = ?");
    $stmt->execute([$id]);
    $participants = $stmt->fetchAll();

    // Votes
    $stmt = $pdo->prepare("SELECT * FROM reunion_votes WHERE reunion_id = ?");
    $stmt->execute([$id]);
    $votes = $stmt->fetchAll();

    // Signatures
    $stmt = $pdo->prepare("SELECT rs.*, u.prenom, u.nom FROM reunion_signatures rs JOIN users u ON rs.user_id = u.id WHERE rs.reunion_id = ?");
    $stmt->execute([$id]);
    $signatures = $stmt->fetchAll();

    $asso = getAssociationSettings();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>PV - <?php echo e($reunion['titre']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12pt; margin: 40px; }
            h1 { text-align: center; color: #2563eb; }
            h2 { color: #1e40af; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
            .header { text-align: center; margin-bottom: 30px; }
            .info { margin-bottom: 20px; }
            .info p { margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #f3f4f6; }
            .signatures { margin-top: 40px; }
            .signature-box { display: inline-block; width: 200px; text-align: center; margin: 20px; }
            .signature-box img { max-width: 150px; }
            @media print { body { margin: 20px; } }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo e($asso['nom'] ?: 'Association'); ?></h1>
            <h2><?php echo e($typesReunion[$reunion['type_reunion']]); ?></h2>
            <h3><?php echo e($reunion['titre']); ?></h3>
        </div>

        <div class="info">
            <p><strong>Date :</strong> <?php echo date('d/m/Y à H:i', strtotime($reunion['date_reunion'])); ?></p>
            <p><strong>Lieu :</strong> <?php echo e($reunion['lieu']); ?></p>
        </div>

        <h2>Participants</h2>
        <table>
            <tr><th>Nom</th><th>Rôle</th><th>Présence</th></tr>
            <?php foreach ($participants as $p): ?>
                <tr>
                    <td><?php echo e($p['nom'] . ' ' . $p['prenom']); ?></td>
                    <td><?php echo e($rolesReunion[$p['role_reunion']] ?? $p['role_reunion']); ?></td>
                    <td><?php echo e($presenceOptions[$p['presence']] ?? $p['presence']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php if ($reunion['ordre_du_jour']): ?>
            <h2>Ordre du jour</h2>
            <div style="white-space: pre-line;"><?php echo e($reunion['ordre_du_jour']); ?></div>
        <?php endif; ?>

        <?php if (!empty($votes)): ?>
            <h2>Votes</h2>
            <table>
                <tr><th>Sujet</th><th>Pour</th><th>Contre</th><th>Abst.</th><th>Résultat</th></tr>
                <?php foreach ($votes as $v): ?>
                    <tr>
                        <td><?php echo e($v['sujet']); ?></td>
                        <td><?php echo $v['votes_pour']; ?></td>
                        <td><?php echo $v['votes_contre']; ?></td>
                        <td><?php echo $v['abstentions']; ?></td>
                        <td><strong><?php echo $v['resultat'] ? ucfirst($v['resultat']) : '-'; ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if ($reunion['compte_rendu']): ?>
            <h2>Compte-rendu</h2>
            <div style="white-space: pre-line;"><?php echo e($reunion['compte_rendu']); ?></div>
        <?php endif; ?>

        <?php if ($reunion['decisions']): ?>
            <h2>Décisions</h2>
            <div style="white-space: pre-line;"><?php echo e($reunion['decisions']); ?></div>
        <?php endif; ?>

        <?php if (!empty($signatures)): ?>
            <div class="signatures">
                <h2>Signatures</h2>
                <?php foreach ($signatures as $sig): ?>
                    <div class="signature-box">
                        <?php if ($sig['signature_data']): ?>
                            <img src="<?php echo e($sig['signature_data']); ?>" alt="Signature">
                        <?php endif; ?>
                        <p><strong><?php echo e($sig['nom'] . ' ' . $sig['prenom']); ?></strong></p>
                        <p><?php echo e($sig['role_signataire']); ?></p>
                        <p><small><?php echo date('d/m/Y', strtotime($sig['signe_at'])); ?></small></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <script>window.print();</script>
    </body>
    </html>
    <?php exit; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

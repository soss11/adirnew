<?php
/**
 * Mini CRM - Import de membres CSV/Excel
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('admin');

$message = '';
$errors = [];
$imported = 0;
$skipped = 0;

// Traitement de l'import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Erreur lors du téléchargement du fichier.';
    } else {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, ['csv', 'txt'])) {
            $message = 'Format de fichier non supporté. Utilisez un fichier CSV.';
        } else {
            // Ouvrir le fichier
            $handle = fopen($file['tmp_name'], 'r');

            if ($handle) {
                // Lire la première ligne (en-têtes)
                $headers = fgetcsv($handle, 0, $_POST['delimiter'] ?? ';');

                if (!$headers) {
                    $message = 'Impossible de lire le fichier CSV.';
                } else {
                    // Normaliser les en-têtes
                    $headers = array_map(function($h) {
                        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $h)));
                    }, $headers);

                    // Mapping des colonnes
                    $mapping = [
                        'nom' => ['nom', 'name', 'lastname', 'surname'],
                        'prenom' => ['prenom', 'firstname', 'givenname'],
                        'email' => ['email', 'mail', 'courriel'],
                        'telephone' => ['telephone', 'tel', 'phone', 'mobile'],
                        'adresse' => ['adresse', 'address'],
                        'ville' => ['ville', 'city'],
                        'code_postal' => ['code_postal', 'codepostal', 'cp', 'zip', 'postalcode'],
                        'pays_origine' => ['pays_origine', 'paysorigine', 'pays', 'country'],
                        'nationalite' => ['nationalite', 'nationality'],
                        'langue_parlee' => ['langue_parlee', 'langue', 'language', 'langues'],
                        'date_naissance' => ['date_naissance', 'datenaissance', 'naissance', 'birthdate', 'dob'],
                        'date_arrivee' => ['date_arrivee', 'datearrivee', 'arrivee'],
                        'profession' => ['profession', 'job', 'metier'],
                        'notes' => ['notes', 'note', 'commentaire', 'comments']
                    ];

                    // Trouver les indices des colonnes
                    $columnIndices = [];
                    foreach ($mapping as $field => $aliases) {
                        foreach ($aliases as $alias) {
                            $index = array_search($alias, $headers);
                            if ($index !== false) {
                                $columnIndices[$field] = $index;
                                break;
                            }
                        }
                    }

                    // Vérifier les colonnes obligatoires
                    if (!isset($columnIndices['nom']) && !isset($columnIndices['prenom'])) {
                        $message = 'Le fichier doit contenir au moins une colonne "nom" ou "prenom".';
                    } elseif (!isset($columnIndices['email'])) {
                        $message = 'Le fichier doit contenir une colonne "email".';
                    } else {
                        // Mot de passe par défaut
                        $defaultPassword = $_POST['default_password'] ?? 'changeme123';
                        $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

                        // Mode d'import
                        $skipExisting = isset($_POST['skip_existing']);

                        $lineNumber = 1;
                        while (($data = fgetcsv($handle, 0, $_POST['delimiter'] ?? ';')) !== false) {
                            $lineNumber++;

                            // Extraire les données
                            $row = [];
                            foreach ($columnIndices as $field => $index) {
                                $row[$field] = isset($data[$index]) ? trim($data[$index]) : '';
                            }

                            // Valider l'email
                            if (empty($row['email']) || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                                $errors[] = "Ligne $lineNumber : Email invalide ou manquant";
                                $skipped++;
                                continue;
                            }

                            // Vérifier si l'email existe déjà
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                            $stmt->execute([$row['email']]);
                            if ($stmt->fetch()) {
                                if ($skipExisting) {
                                    $skipped++;
                                    continue;
                                } else {
                                    $errors[] = "Ligne $lineNumber : L'email {$row['email']} existe déjà";
                                    $skipped++;
                                    continue;
                                }
                            }

                            // Formater les dates
                            foreach (['date_naissance', 'date_arrivee'] as $dateField) {
                                if (!empty($row[$dateField])) {
                                    $timestamp = strtotime($row[$dateField]);
                                    if ($timestamp) {
                                        $row[$dateField] = date('Y-m-d', $timestamp);
                                    } else {
                                        $row[$dateField] = null;
                                    }
                                } else {
                                    $row[$dateField] = null;
                                }
                            }

                            // Insérer le membre
                            try {
                                $stmt = $pdo->prepare("INSERT INTO users
                                    (nom, prenom, email, password, telephone, adresse, ville, code_postal,
                                     pays_origine, nationalite, langue_parlee, date_naissance, date_arrivee,
                                     profession, notes, role, actif, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'membre', 1, NOW())");

                                $stmt->execute([
                                    $row['nom'] ?? '',
                                    $row['prenom'] ?? '',
                                    $row['email'],
                                    $passwordHash,
                                    $row['telephone'] ?? null,
                                    $row['adresse'] ?? null,
                                    $row['ville'] ?? null,
                                    $row['code_postal'] ?? null,
                                    $row['pays_origine'] ?? null,
                                    $row['nationalite'] ?? null,
                                    $row['langue_parlee'] ?? null,
                                    $row['date_naissance'],
                                    $row['date_arrivee'],
                                    $row['profession'] ?? null,
                                    $row['notes'] ?? null
                                ]);

                                $imported++;
                            } catch (PDOException $e) {
                                $errors[] = "Ligne $lineNumber : Erreur d'insertion - " . $e->getMessage();
                                $skipped++;
                            }
                        }

                        if ($imported > 0) {
                            setFlashMessage('success', "$imported membre(s) importé(s) avec succès. $skipped ignoré(s).");
                        } else {
                            setFlashMessage('error', "Aucun membre importé. $skipped ligne(s) ignorée(s).");
                        }
                    }
                }

                fclose($handle);
            }
        }
    }
}

// Maintenant inclure le header (après tout le traitement POST)
$pageTitle = 'Import CSV';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.import-container {
    max-width: 800px;
}
.upload-zone {
    border: 2px dashed #ddd;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: #fafafa;
    margin-bottom: 30px;
    transition: all 0.3s;
}
.upload-zone:hover {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.05);
}
.upload-zone input[type="file"] {
    display: none;
}
.upload-zone label {
    cursor: pointer;
    display: block;
}
.upload-zone .icon {
    font-size: 48px;
    margin-bottom: 15px;
}
.upload-zone h3 {
    margin: 0 0 10px;
}
.upload-zone p {
    color: #666;
    margin: 0;
}
.format-info {
    background: #f0f4ff;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
.format-info h4 {
    margin: 0 0 10px;
    color: var(--primary);
}
.format-info ul {
    margin: 0;
    padding-left: 20px;
}
.format-info li {
    margin: 5px 0;
}
.import-options {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #eee;
    margin-bottom: 20px;
}
.import-options h4 {
    margin: 0 0 15px;
}
.option-group {
    margin-bottom: 15px;
}
.option-group label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}
.option-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
}
.errors-list {
    background: #fff5f5;
    border: 1px solid #ffcccc;
    border-radius: 10px;
    padding: 15px;
    margin-top: 20px;
    max-height: 200px;
    overflow-y: auto;
}
.errors-list h4 {
    color: #c62828;
    margin: 0 0 10px;
}
.errors-list ul {
    margin: 0;
    padding-left: 20px;
    font-size: 13px;
}
.template-download {
    margin-top: 30px;
    padding: 20px;
    background: #f5f5f5;
    border-radius: 10px;
    text-align: center;
}
</style>

<div class="import-container">
    <div class="card">
        <div class="card-header">
            <h3>Importer des membres</h3>
        </div>
        <div class="card-body">

            <div class="format-info">
                <h4>Format du fichier CSV</h4>
                <p>Le fichier doit contenir les colonnes suivantes (la première ligne doit être les en-têtes) :</p>
                <ul>
                    <li><strong>email</strong> (obligatoire) - Adresse email du membre</li>
                    <li><strong>nom</strong> - Nom de famille</li>
                    <li><strong>prenom</strong> - Prénom</li>
                    <li><strong>telephone</strong> - Numéro de téléphone</li>
                    <li><strong>adresse</strong> - Adresse postale</li>
                    <li><strong>ville</strong> - Ville</li>
                    <li><strong>code_postal</strong> - Code postal</li>
                    <li><strong>pays_origine</strong> - Pays d'origine</li>
                    <li><strong>nationalite</strong> - Nationalité</li>
                    <li><strong>langue_parlee</strong> - Langues parlées</li>
                    <li><strong>date_naissance</strong> - Date de naissance (YYYY-MM-DD ou DD/MM/YYYY)</li>
                    <li><strong>date_arrivee</strong> - Date d'arrivée dans le pays</li>
                    <li><strong>profession</strong> - Profession</li>
                    <li><strong>notes</strong> - Notes/commentaires</li>
                </ul>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="upload-zone" id="dropZone">
                    <input type="file" name="csv_file" id="csvFile" accept=".csv,.txt" required>
                    <label for="csvFile">
                        <div class="icon">&#128196;</div>
                        <h3>Glissez votre fichier CSV ici</h3>
                        <p>ou cliquez pour sélectionner un fichier</p>
                        <p id="fileName" style="color:var(--primary);margin-top:10px;font-weight:500;"></p>
                    </label>
                </div>

                <div class="import-options">
                    <h4>Options d'import</h4>

                    <div class="option-group">
                        <label>
                            Délimiteur :
                            <select name="delimiter" class="form-control" style="width:auto;display:inline-block;margin-left:10px;">
                                <option value=";">Point-virgule (;)</option>
                                <option value=",">Virgule (,)</option>
                                <option value="&#9;">Tabulation</option>
                            </select>
                        </label>
                    </div>

                    <div class="option-group">
                        <label>
                            <input type="checkbox" name="skip_existing" checked>
                            Ignorer les membres existants (basé sur l'email)
                        </label>
                    </div>

                    <div class="option-group">
                        <label>
                            Mot de passe par défaut :
                            <input type="text" name="default_password" value="changeme123" class="form-control" style="width:200px;display:inline-block;margin-left:10px;">
                        </label>
                        <p style="font-size:12px;color:#666;margin:5px 0 0 28px;">Les membres devront changer leur mot de passe à la première connexion.</p>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
                    Importer les membres
                </button>
            </form>

            <?php if (!empty($errors)): ?>
            <div class="errors-list">
                <h4>Erreurs rencontrées (<?php echo count($errors); ?>)</h4>
                <ul>
                    <?php foreach (array_slice($errors, 0, 20) as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                    <?php if (count($errors) > 20): ?>
                        <li>... et <?php echo count($errors) - 20; ?> autres erreurs</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="template-download">
                <p>Besoin d'un modèle ?</p>
                <a href="import-template.php" class="btn btn-secondary">Télécharger le modèle CSV</a>
            </div>

        </div>
    </div>
</div>

<script>
// Afficher le nom du fichier sélectionné
document.getElementById('csvFile').addEventListener('change', function(e) {
    document.getElementById('fileName').textContent = this.files[0] ? this.files[0].name : '';
});

// Drag & Drop
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('csvFile');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.style.borderColor = 'var(--primary)', false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, () => dropZone.style.borderColor = '#ddd', false);
});

dropZone.addEventListener('drop', function(e) {
    const files = e.dataTransfer.files;
    if (files.length) {
        fileInput.files = files;
        document.getElementById('fileName').textContent = files[0].name;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

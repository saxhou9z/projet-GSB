<?php
require_once __DIR__ . '/config.php';
requireLogin();

// Visiteurs: read-only. Delegue/chef: full CRUD.
$user = getCurrentUser();
$role = $_SESSION['user_role'];
$canEdit = in_array($role, ['delegue', 'chef']);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code        = trim($_POST['code'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $prix        = $_POST['prix'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $famille_id  = $_POST['famille_id'] ?: null;

        if (!empty($code) && !empty($designation) && !empty($prix)) {
            $stmt = $pdo->prepare("INSERT INTO medicaments (code, designation, famille_id, prix, description) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$code, $designation, $famille_id, $prix, $description])) {
                $message = 'Médicament ajouté avec succès';
                $messageType = 'success';
            }
        } else {
            $message = 'Veuillez remplir tous les champs obligatoires';
            $messageType = 'error';
        }

    } elseif ($action === 'edit') {
        $id          = $_POST['id'] ?? '';
        $code        = trim($_POST['code'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $prix        = $_POST['prix'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $famille_id  = $_POST['famille_id'] ?: null;

        if (!empty($id) && !empty($code) && !empty($designation)) {
            $stmt = $pdo->prepare("UPDATE medicaments SET code=?, designation=?, famille_id=?, prix=?, description=? WHERE id=?");
            if ($stmt->execute([$code, $designation, $famille_id, $prix, $description, $id])) {
                $message = 'Médicament modifié avec succès';
                $messageType = 'success';
            }
        }

    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if (!empty($id)) {
            $stmt = $pdo->prepare("DELETE FROM medicaments WHERE id = ?");
            if ($stmt->execute([$id])) {
                $message = 'Médicament supprimé';
                $messageType = 'success';
            }
        }
    }
}

$medicaments = $pdo->query("SELECT m.*, f.libelle as famille FROM medicaments m LEFT JOIN familles f ON m.famille_id=f.id ORDER BY m.designation")->fetchAll();
$familles    = $pdo->query("SELECT * FROM familles ORDER BY libelle")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Médicaments - GSB Visite</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/header.php'; ?>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($canEdit): ?>
        <!-- Formulaire ajout (delegue + chef seulement) -->
        <section class="card" style="margin-bottom:2rem; padding:1.5rem;">
            <h2>Ajouter un médicament</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr 2fr; gap:1rem; margin-bottom:1rem;">
                    <div class="input-group">
                        <label>Code *</label>
                        <input type="text" name="code" placeholder="MED001" required class="form-control">
                    </div>
                    <div class="input-group">
                        <label>Désignation *</label>
                        <input type="text" name="designation" placeholder="Nom du médicament" required class="form-control">
                    </div>
                    <div class="input-group">
                        <label>Famille</label>
                        <select name="famille_id" class="form-control">
                            <option value="">— Aucune —</option>
                            <?php foreach ($familles as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Prix (€) *</label>
                        <input type="number" name="prix" step="0.01" min="0" placeholder="0.00" required class="form-control">
                    </div>
                    <div class="input-group">
                        <label>Description</label>
                        <input type="text" name="description" placeholder="Description..." class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn-add">+ Ajouter</button>
            </form>
        </section>
        <?php endif; ?>

        <!-- Liste -->
        <section class="card" style="padding:1.5rem;">
            <h2>Liste des médicaments (<?= count($medicaments) ?>)</h2>
            <?php if (empty($medicaments)): ?>
                <p style="color:#666; text-align:center; padding:2rem;">Aucun médicament</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Désignation</th>
                            <th>Famille</th>
                            <th>Prix</th>
                            <th>Description</th>
                            <?php if ($canEdit): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicaments as $med): ?>
                            <tr>
                                <td><?= htmlspecialchars($med['code']) ?></td>
                                <td><?= htmlspecialchars($med['designation']) ?></td>
                                <td><span class="famille-tag"><?= htmlspecialchars($med['famille'] ?? '—') ?></span></td>
                                <td><?= number_format($med['prix'], 2) ?> €</td>
                                <td><?= htmlspecialchars($med['description'] ?? '') ?></td>
                                <?php if ($canEdit): ?>
                                <td>
                                    <button class="btn-small btn-edit"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($med)) ?>)">
                                        ✎ Modifier
                                    </button>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Supprimer ce médicament ?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $med['id'] ?>">
                                        <button type="submit" class="btn-small btn-danger">✕ Supprimer</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($canEdit): ?>
    <div id="editModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; margin:10% auto; padding:2rem; border-radius:12px; max-width:500px;">
            <h2>Modifier le médicament</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="input-group">
                    <label>Code *</label>
                    <input type="text" name="code" id="edit_code" required class="form-control">
                </div>
                <div class="input-group">
                    <label>Désignation *</label>
                    <input type="text" name="designation" id="edit_designation" required class="form-control">
                </div>
                <div class="input-group">
                    <label>Famille</label>
                    <select name="famille_id" id="edit_famille_id" class="form-control">
                        <option value="">— Aucune —</option>
                        <?php foreach ($familles as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>Prix (€) *</label>
                    <input type="number" name="prix" id="edit_prix" step="0.01" min="0" required class="form-control">
                </div>
                <div class="input-group">
                    <label>Description</label>
                    <input type="text" name="description" id="edit_description" class="form-control">
                </div>
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <button type="submit" class="btn-primary" style="flex:1;">Enregistrer</button>
                    <button type="button" onclick="closeModal('editModal')" class="btn btn-logout" style="flex:1;">Annuler</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openEditModal(med) {
            document.getElementById('edit_id').value = med.id;
            document.getElementById('edit_code').value = med.code;
            document.getElementById('edit_designation').value = med.designation;
            document.getElementById('edit_prix').value = med.prix;
            document.getElementById('edit_description').value = med.description || '';
            const sel = document.getElementById('edit_famille_id');
            if (sel) sel.value = med.famille_id || '';
            document.getElementById('editModal').style.display = 'block';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        window.onclick = function(event) {
            if (event.target.id === 'editModal') event.target.style.display = 'none';
        }
    </script>
</body>
</html>

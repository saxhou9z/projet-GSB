<?php
require_once __DIR__ . '/config.php';
requireRole('chef');

$uid = $_SESSION['user_id'];
$user = getCurrentUser();
$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $role   = $_POST['role'] ?? 'visiteur';
        $pass   = $_POST['password'] ?? '';
        $region = $_POST['region_id'] ?: null;
        $resp   = $_POST['responsable_id'] ?: null;

        if (!in_array($role, ['visiteur', 'delegue'])) $role = 'visiteur';

        if ($nom && $prenom && $email && strlen($pass) >= 6) {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, region_id, responsable_id) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$nom, $prenom, $email, $hash, $role, $region, $resp]);
                $message = "✅ $prenom $nom créé avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        } else {
            $error = "Tous les champs sont obligatoires (mot de passe min. 6 caractères).";
        }
    }

    if ($action === 'update_role') {
        $tid   = (int)$_POST['target_id'];
        $nrole = $_POST['new_role'] ?? '';
        if ($tid && in_array($nrole, ['visiteur', 'delegue'])) {
            $pdo->prepare("UPDATE utilisateurs SET role=? WHERE id=?")->execute([$nrole, $tid]);
            $message = "✅ Rôle mis à jour.";
        }
    }

    if ($action === 'assign_responsable') {
        $tid  = (int)$_POST['target_id'];
        $resp = $_POST['responsable_id'] ?: null;
        $pdo->prepare("UPDATE utilisateurs SET responsable_id=? WHERE id=? AND role='visiteur'")->execute([$resp, $tid]);
        $message = "✅ Responsable mis à jour.";
    }

    if ($action === 'delete_user') {
        $tid = (int)$_POST['target_id'];
        if ($tid && $tid !== $uid) {
            $pdo->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$tid]);
            $message = "✅ Utilisateur supprimé.";
        } else {
            $error = "Impossible de supprimer votre propre compte.";
        }
    }

    if ($action === 'reset_password') {
        $tid  = (int)$_POST['target_id'];
        $pass = $_POST['new_password'] ?? '';
        if ($tid && strlen($pass) >= 6) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE id=?")->execute([$hash, $tid]);
            $message = "✅ Mot de passe réinitialisé.";
        } else {
            $error = "Mot de passe trop court (min. 6 caractères).";
        }
    }
}

// Data
$utilisateurs = $pdo->query("SELECT u.*, r.nom as region_nom FROM utilisateurs u LEFT JOIN regions r ON u.region_id=r.id WHERE u.role IN ('visiteur','delegue') ORDER BY u.role DESC, u.nom")->fetchAll();
$regions      = $pdo->query("SELECT * FROM regions ORDER BY nom")->fetchAll();
$delegues     = $pdo->query("SELECT * FROM utilisateurs WHERE role='delegue' ORDER BY nom")->fetchAll();

$nbDelegues  = count(array_filter($utilisateurs, fn($u) => $u['role'] === 'delegue'));
$nbVisiteurs = count(array_filter($utilisateurs, fn($u) => $u['role'] === 'visiteur'));

$nbRdvTotal    = $pdo->query("SELECT COUNT(*) FROM rendez_vous")->fetchColumn();
$nbRdvEffectue = $pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut='effectué'")->fetchColumn();
$tauxGlobal    = $nbRdvTotal > 0 ? round($nbRdvEffectue/$nbRdvTotal*100) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de l'équipe – GSB Visite</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:1.5rem;">
        <div class="stat-card"><h3>Délégués</h3><div class="value"><?= $nbDelegues ?></div></div>
        <div class="stat-card"><h3>Visiteurs</h3><div class="value"><?= $nbVisiteurs ?></div></div>
        <div class="stat-card"><h3>RDV total</h3><div class="value"><?= $nbRdvTotal ?></div></div>
        <div class="stat-card"><h3>Taux global</h3><div class="value"><?= $tauxGlobal ?>%</div></div>
    </div>

    <div class="main-grid">
        <!-- Formulaire création -->
        <section class="card" style="padding:1.5rem;">
            <h2>➕ Créer un compte</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="input-group">
                    <label>Prénom *</label>
                    <input type="text" name="prenom" class="form-control" required>
                </div>
                <div class="input-group">
                    <label>Nom *</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="input-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="input-group">
                    <label>Mot de passe * (min. 6 car.)</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="input-group">
                    <label>Rôle *</label>
                    <select name="role" class="form-control" id="roleSelect" onchange="toggleResp()">
                        <option value="visiteur">Visiteur médical</option>
                        <option value="delegue">Délégué régional</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Région</label>
                    <select name="region_id" class="form-control">
                        <option value="">— Aucune —</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group" id="respField">
                    <label>Délégué responsable</label>
                    <select name="responsable_id" class="form-control">
                        <option value="">— Aucun —</option>
                        <?php foreach ($delegues as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['prenom'].' '.$d['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn" style="width:100%; margin-top:1rem;">Créer le compte</button>
            </form>
        </section>

        <!-- Liste utilisateurs -->
        <section class="card" style="padding:1.5rem;">
            <h2>👥 Délégués & Visiteurs</h2>
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('tous', this)">Tous (<?= count($utilisateurs) ?>)</button>
                <button class="tab-btn" onclick="showTab('delegues', this)">Délégués (<?= $nbDelegues ?>)</button>
                <button class="tab-btn" onclick="showTab('visiteurs', this)">Visiteurs (<?= $nbVisiteurs ?>)</button>
            </div>
            <input type="text" class="form-control" id="searchUser" placeholder="🔍 Rechercher..." oninput="filterTable()" style="margin:1rem 0;">

            <div id="tab-tous" class="tab-content active">
                <table class="table" id="userTable">
                    <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Région</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                        <tr data-role="<?= $u['role'] ?>">
                            <td><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge badge-role-<?= $u['role'] ?>"><?= $u['role'] === 'delegue' ? 'Délégué' : 'Visiteur' ?></span></td>
                            <td><?= htmlspecialchars($u['region_nom'] ?? '—') ?></td>
                            <td>
                                <button class="btn-small btn-edit"
                                    onclick="openRoleModal(<?= $u['id'] ?>,'<?= $u['role'] ?>','<?= htmlspecialchars($u['prenom'].' '.$u['nom'], ENT_QUOTES) ?>')">
                                    ✏️ Rôle
                                </button>
                                <button class="btn-small btn-edit"
                                    onclick="openPwdModal(<?= $u['id'] ?>,'<?= htmlspecialchars($u['prenom'].' '.$u['nom'], ENT_QUOTES) ?>')">
                                    🔑
                                </button>
                                <?php if ($u['id'] !== $uid): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cet utilisateur et toutes ses données ?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-small btn-danger">🗑️</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<!-- Modal rôle -->
<div class="modal-overlay" id="roleModal">
    <div class="modal">
        <h3>✏️ Modifier le rôle</h3>
        <p id="roleModalName" style="color:var(--text-muted); margin-bottom:1rem;"></p>
        <form method="POST">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="target_id" id="roleTargetId">
            <div class="input-group">
                <label>Nouveau rôle</label>
                <select name="new_role" id="roleSelect2" class="form-control">
                    <option value="visiteur">Visiteur médical</option>
                    <option value="delegue">Délégué régional</option>
                </select>
            </div>
            <div style="display:flex; gap:.5rem; margin-top:1rem;">
                <button type="submit" class="btn">Enregistrer</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('roleModal')">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal mot de passe -->
<div class="modal-overlay" id="pwdModal">
    <div class="modal">
        <h3>🔑 Réinitialiser le mot de passe</h3>
        <p id="pwdModalName" style="color:var(--text-muted); margin-bottom:1rem;"></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_id" id="pwdTargetId">
            <div class="input-group">
                <label>Nouveau mot de passe (min. 6 caractères)</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <div style="display:flex; gap:.5rem; margin-top:1rem;">
                <button type="submit" class="btn">Enregistrer</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('pwdModal')">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(name, btn) {
    const rows = document.querySelectorAll('#userTable tbody tr');
    rows.forEach(r => {
        if (name === 'tous') r.style.display = '';
        else r.style.display = r.dataset.role === name.replace('s','') ? '' : 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}
function openRoleModal(id, role, name) {
    document.getElementById('roleTargetId').value = id;
    document.getElementById('roleModalName').textContent = name;
    document.getElementById('roleSelect2').value = role;
    document.getElementById('roleModal').classList.add('open');
}
function openPwdModal(id, name) {
    document.getElementById('pwdTargetId').value = id;
    document.getElementById('pwdModalName').textContent = name;
    document.getElementById('pwdModal').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});
function filterTable() {
    const val = document.getElementById('searchUser').value.toLowerCase();
    document.querySelectorAll('#userTable tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
function toggleResp() {
    const role = document.getElementById('roleSelect').value;
    document.getElementById('respField').style.display = role === 'visiteur' ? '' : 'none';
}
</script>
</body>
</html>

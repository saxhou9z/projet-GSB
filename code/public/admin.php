<?php
require_once __DIR__ . '/config.php';
requireRole('admin', 'chef');

$user = getCurrentUser();
$uid  = $_SESSION['user_id'];
$message = '';
$error   = '';

// ── Actions POST ─────────────────────────────────────────────────────────────
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

        if ($nom && $prenom && $email && $pass) {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO utilisateurs (nom,prenom,email,password,role,region_id,responsable_id)
                               VALUES (?,?,?,?,?,?,?)")
                    ->execute([$nom, $prenom, $email, $hash, $role, $region, $resp]);
                $message = "✅ Utilisateur $prenom $nom créé avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        } else {
            $error = "Tous les champs obligatoires doivent être remplis.";
        }
    }

    if ($action === 'update_role') {
        $tid  = (int)$_POST['target_id'];
        $nrole = $_POST['new_role'] ?? '';
        if ($tid && in_array($nrole, ['visiteur','delegue','chef'])) {
            $pdo->prepare("UPDATE utilisateurs SET role=? WHERE id=?")->execute([$nrole, $tid]);
            $message = "✅ Rôle mis à jour.";
        }
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
            $pdo->prepare("UPDATE utilisateurs SET password=? WHERE id=?")->execute([$hash, $tid]);
            $message = "✅ Mot de passe réinitialisé.";
        } else {
            $error = "Mot de passe trop court (min. 6 caractères).";
        }
    }
}

// ── Données ──────────────────────────────────────────────────────────────────
$utilisateurs    = $pdo->query("SELECT u.*, r.nom as region_nom FROM utilisateurs u LEFT JOIN regions r ON u.region_id=r.id ORDER BY u.nom")->fetchAll();
$regions         = $pdo->query("SELECT * FROM regions ORDER BY nom")->fetchAll();
$delegues        = $pdo->query("SELECT * FROM utilisateurs WHERE role='delegue' ORDER BY nom")->fetchAll();

$nbVisiteurs     = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='visiteur'")->fetchColumn();
$nbDelegues      = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='delegue'")->fetchColumn();
$nbChefs         = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='chef'")->fetchColumn();
$nbProfessionnels= $pdo->query("SELECT COUNT(*) FROM professionnels")->fetchColumn();
$nbMedicaments   = $pdo->query("SELECT COUNT(*) FROM medicaments")->fetchColumn();
$nbRdvTotal      = $pdo->query("SELECT COUNT(*) FROM rendez_vous")->fetchColumn();
$nbRdvEffectues  = $pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut='effectué'")->fetchColumn();
$tauxGlobal      = $nbRdvTotal > 0 ? round($nbRdvEffectues / $nbRdvTotal * 100) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Visiteur Médical</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <!-- ALERTES -->
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid" style="margin-bottom:1.5rem;">
        <div class="stat-card">
            <span class="stat-icon">👤</span>
            <h3>Visiteurs</h3>
            <div class="value"><?= $nbVisiteurs ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🏅</span>
            <h3>Délégués</h3>
            <div class="value"><?= $nbDelegues ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⭐</span>
            <h3>Chefs</h3>
            <div class="value"><?= $nbChefs ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">👨‍⚕️</span>
            <h3>Professionnels</h3>
            <div class="value"><?= $nbProfessionnels ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">💊</span>
            <h3>Médicaments</h3>
            <div class="value"><?= $nbMedicaments ?></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <h3>Taux visites</h3>
            <div class="value"><?= $tauxGlobal ?>%</div>
        </div>
    </div>

    <!-- GRILLE PRINCIPALE -->
    <div class="main-grid">

        <!-- COLONNE GAUCHE : Créer un utilisateur -->
        <section>
            <h2>➕ Nouvel utilisateur</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="form-row">
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe *</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Rôle *</label>
                    <select name="role" class="form-control">
                        <option value="visiteur">Visiteur médical</option>
                        <option value="delegue">Délégué régional</option>
                        <option value="chef">Chef régional</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Région</label>
                    <select name="region_id" class="form-control">
                        <option value="">— Aucune —</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Responsable (délégué)</label>
                    <select name="responsable_id" class="form-control">
                        <option value="">— Aucun —</option>
                        <?php foreach ($delegues as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['prenom'].' '.$d['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn" style="width:100%;">Créer l'utilisateur</button>
            </form>
        </section>

        <!-- COLONNE DROITE : Liste utilisateurs -->
        <section>
            <div class="section-header">
                <h2>👥 Utilisateurs</h2>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="showTab('tous')">Tous (<?= count($utilisateurs) ?>)</button>
                <button class="tab-btn" onclick="showTab('visiteurs')">Visiteurs (<?= $nbVisiteurs ?>)</button>
                <button class="tab-btn" onclick="showTab('delegues')">Délégués (<?= $nbDelegues ?>)</button>
            </div>

            <input type="text" class="search-bar" id="searchUser"
                   placeholder="🔍 Rechercher un utilisateur…"
                   oninput="filterTable()">

            <!-- Tab : Tous -->
            <div class="tab-content active" id="tab-tous">
                <table class="table" id="userTable">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Région</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge badge-role-<?= $u['role'] ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
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
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Supprimer cet utilisateur ?')">
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

            <!-- Tab : Visiteurs -->
            <div class="tab-content" id="tab-visiteurs">
                <table class="table">
                    <thead>
                        <tr><th>Nom</th><th>Email</th><th>Responsable</th><th>Région</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                        <?php if ($u['role'] !== 'visiteur') continue; ?>
                        <tr>
                            <td><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php
                                if ($u['responsable_id']) {
                                    $resp = array_filter($utilisateurs, fn($x) => $x['id'] == $u['responsable_id']);
                                    $resp = array_values($resp)[0] ?? null;
                                    echo $resp ? htmlspecialchars($resp['prenom'].' '.$resp['nom']) : '—';
                                } else { echo '—'; }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($u['region_nom'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab : Délégués -->
            <div class="tab-content" id="tab-delegues">
                <table class="table">
                    <thead>
                        <tr><th>Nom</th><th>Email</th><th>Région</th><th>Équipe</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                        <?php if ($u['role'] !== 'delegue') continue; ?>
                        <tr>
                            <td><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['region_nom'] ?? '—') ?></td>
                            <td>
                                <?php
                                $equipe = array_filter($utilisateurs, fn($x) => $x['responsable_id'] == $u['id']);
                                echo count($equipe) . ' visiteur(s)';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div><!-- /main-grid -->
</div><!-- /container -->

<!-- Modal : Modifier le rôle -->
<div class="modal-overlay" id="roleModal">
    <div class="modal">
        <h3>✏️ Modifier le rôle</h3>
        <p id="roleModalName" style="color:var(--text-muted); margin-bottom:1rem;"></p>
        <form method="POST">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="target_id" id="roleTargetId">
            <div class="form-group">
                <label>Nouveau rôle</label>
                <select name="new_role" id="roleSelect" class="form-control">
                    <option value="visiteur">Visiteur médical</option>
                    <option value="delegue">Délégué régional</option>
                    <option value="chef">Chef régional</option>
                </select>
            </div>
            <div style="display:flex; gap:.5rem; margin-top:1rem;">
                <button type="submit" class="btn">Enregistrer</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('roleModal')">Annuler</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : Reset mot de passe -->
<div class="modal-overlay" id="pwdModal">
    <div class="modal">
        <h3>🔑 Réinitialiser le mot de passe</h3>
        <p id="pwdModalName" style="color:var(--text-muted); margin-bottom:1rem;"></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_id" id="pwdTargetId">
            <div class="form-group">
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
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}

function openRoleModal(id, role, name) {
    document.getElementById('roleTargetId').value = id;
    document.getElementById('roleModalName').textContent = name;
    document.getElementById('roleSelect').value = role;
    document.getElementById('roleModal').classList.add('open');
}

function openPwdModal(id, name) {
    document.getElementById('pwdTargetId').value = id;
    document.getElementById('pwdModalName').textContent = name;
    document.getElementById('pwdModal').classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});

function filterTable() {
    const val = document.getElementById('searchUser').value.toLowerCase();
    document.querySelectorAll('#userTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
</script>
</body>
</html>
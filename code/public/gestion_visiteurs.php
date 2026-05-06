<?php
require_once __DIR__ . '/config.php';
requireRole('delegue');

$uid = $_SESSION['user_id'];
$user = getCurrentUser();
$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_visiteur') {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $pass   = $_POST['password'] ?? '';

        if ($nom && $prenom && $email && strlen($pass) >= 6) {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, region_id, responsable_id) VALUES (?,?,?,?,'visiteur',?,?)")
                    ->execute([$nom, $prenom, $email, $hash, $user['region_id'], $uid]);
                $message = "✅ Visiteur $prenom $nom créé avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        } else {
            $error = "Tous les champs sont obligatoires (mot de passe min. 6 caractères).";
        }
    }

    if ($action === 'delete_visiteur') {
        $tid = (int)$_POST['target_id'];
        // Verify this visiteur belongs to this delegue
        $chk = $pdo->prepare("SELECT id FROM utilisateurs WHERE id=? AND responsable_id=? AND role='visiteur'");
        $chk->execute([$tid, $uid]);
        if ($chk->fetchColumn()) {
            $pdo->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$tid]);
            $message = "✅ Visiteur supprimé.";
        } else {
            $error = "Visiteur introuvable.";
        }
    }

    if ($action === 'reset_password') {
        $tid  = (int)$_POST['target_id'];
        $pass = $_POST['new_password'] ?? '';
        $chk = $pdo->prepare("SELECT id FROM utilisateurs WHERE id=? AND responsable_id=? AND role='visiteur'");
        $chk->execute([$tid, $uid]);
        if ($chk->fetchColumn() && strlen($pass) >= 6) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE utilisateurs SET mot_de_passe=? WHERE id=?")->execute([$hash, $tid]);
            $message = "✅ Mot de passe réinitialisé.";
        } else {
            $error = "Erreur ou mot de passe trop court.";
        }
    }
}

$visiteurs = [];
$stmt = $pdo->prepare("SELECT u.*, r.nom as region_nom,
    (SELECT COUNT(*) FROM rendez_vous WHERE utilisateur_id=u.id) as nb_rdv,
    (SELECT COUNT(*) FROM rendez_vous WHERE utilisateur_id=u.id AND statut='effectué') as nb_effectues
    FROM utilisateurs u LEFT JOIN regions r ON u.region_id=r.id
    WHERE u.responsable_id=? AND u.role='visiteur' ORDER BY u.nom");
$stmt->execute([$uid]);
$visiteurs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de mes visiteurs – GSB Visite</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="main-grid">
        <!-- Créer un visiteur -->
        <section class="card" style="padding:1.5rem;">
            <h2>➕ Ajouter un visiteur</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_visiteur">
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
                <button type="submit" class="btn" style="width:100%; margin-top:1rem;">Créer le visiteur</button>
            </form>
        </section>

        <!-- Liste visiteurs -->
        <section class="card" style="padding:1.5rem;">
            <h2>👥 Mes visiteurs (<?= count($visiteurs) ?>)</h2>
            <?php if (empty($visiteurs)): ?>
                <p style="color:var(--text-muted); padding:2rem; text-align:center;">Aucun visiteur dans votre équipe.</p>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>RDV total</th>
                        <th>Effectués</th>
                        <th>Taux</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visiteurs as $v): ?>
                    <?php $taux = $v['nb_rdv'] > 0 ? round($v['nb_effectues']/$v['nb_rdv']*100) : 0; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['prenom'].' '.$v['nom']) ?></strong></td>
                        <td><?= htmlspecialchars($v['email']) ?></td>
                        <td><?= $v['nb_rdv'] ?></td>
                        <td><?= $v['nb_effectues'] ?></td>
                        <td><span class="badge badge-<?= $taux >= 70 ? 'effectué' : ($taux >= 40 ? 'planifié' : 'annulé') ?>"><?= $taux ?>%</span></td>
                        <td>
                            <button class="btn-small btn-edit" onclick="openPwdModal(<?= $v['id'] ?>,'<?= htmlspecialchars($v['prenom'].' '.$v['nom'], ENT_QUOTES) ?>')">🔑 MDP</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce visiteur et tous ses RDV ?')">
                                <input type="hidden" name="action" value="delete_visiteur">
                                <input type="hidden" name="target_id" value="<?= $v['id'] ?>">
                                <button type="submit" class="btn-small btn-danger">🗑 Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- Modal reset MDP -->
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
function openPwdModal(id, name) {
    document.getElementById('pwdTargetId').value = id;
    document.getElementById('pwdModalName').textContent = name;
    document.getElementById('pwdModal').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});
</script>
</body>
</html>

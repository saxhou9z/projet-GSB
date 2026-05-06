<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = getCurrentUser();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'visiteur';

$canEdit   = in_array($role, ['chef', 'delegue']);
$canDelete = $role === 'chef';

$message = '';
$error   = '';

// ── Actions POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nom       = trim($_POST['nom'] ?? '');
        $prenom    = trim($_POST['prenom'] ?? '');
        $metier    = trim($_POST['metier'] ?? '');
        $adresse   = trim($_POST['adresse'] ?? '');
        $ville     = trim($_POST['ville'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $code      = 'PDS' . str_pad(rand(100,999), 3, '0', STR_PAD_LEFT);

        if ($nom && $prenom && $metier) {
            try {
                $pdo->prepare("INSERT INTO professionnels (code,nom,prenom,metier,adresse,ville,telephone,email) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$code,$nom,$prenom,$metier,$adresse,$ville,$telephone,$email]);
                $message = "✅ Professionnel $prenom $nom ajouté avec succès.";
            } catch (PDOException $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        } else {
            $error = "Nom, prénom et métier sont obligatoires.";
        }
    }

    if ($action === 'update') {
        $id        = (int)$_POST['pro_id'];
        $nom       = trim($_POST['nom'] ?? '');
        $prenom    = trim($_POST['prenom'] ?? '');
        $metier    = trim($_POST['metier'] ?? '');
        $adresse   = trim($_POST['adresse'] ?? '');
        $ville     = trim($_POST['ville'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if ($nom && $prenom && $metier) {
            try {
                $pdo->prepare("UPDATE professionnels SET nom=?,prenom=?,metier=?,adresse=?,ville=?,telephone=?,email=? WHERE id=?")
                    ->execute([$nom,$prenom,$metier,$adresse,$ville,$telephone,$email,$id]);
                $message = "✅ Professionnel mis à jour.";
            } catch (PDOException $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        } else {
            $error = "Nom, prénom et métier sont obligatoires.";
        }
    }

    if ($action === 'delete' && $canDelete) {
        $id = (int)$_POST['pro_id'];
        try {
            $pdo->prepare("DELETE FROM professionnels WHERE id=?")->execute([$id]);
            $message = "✅ Professionnel supprimé.";
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// ── Filtres & pagination ──────────────────────────────────────────────────────
// Utiliser pnum pour éviter le conflit avec le paramètre 'page' du router
$search_nom   = $_GET['search_nom']   ?? '';
$search_spec  = $_GET['search_spec']  ?? '';
$search_ville = $_GET['search_ville'] ?? '';
$pnum         = max(1, (int)($_GET['pnum'] ?? 1));
$per_page     = 10;

$where  = ['1=1'];
$params = [];

if ($search_nom) {
    $where[]  = "(nom LIKE ? OR prenom LIKE ?)";
    $params[] = "%$search_nom%";
    $params[] = "%$search_nom%";
}
if ($search_spec) {
    $where[]  = "metier = ?";
    $params[] = $search_spec;
}
if ($search_ville) {
    $where[]  = "ville LIKE ?";
    $params[] = "%$search_ville%";
}

$whereStr = implode(' AND ', $where);

try {
    $total = $pdo->prepare("SELECT COUNT(*) FROM professionnels WHERE $whereStr");
    $total->execute($params);
    $total = (int)$total->fetchColumn();
} catch (PDOException $e) {
    $error = "Erreur BD : " . $e->getMessage();
    $total = 0;
}

$pages  = max(1, ceil($total / $per_page));
$offset = ($pnum - 1) * $per_page;

try {
    $stmt = $pdo->prepare("SELECT * FROM professionnels WHERE $whereStr ORDER BY nom, prenom LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $professionnels = $stmt->fetchAll();
} catch (PDOException $e) {
    $error          = "Erreur BD : " . $e->getMessage();
    $professionnels = [];
}

$specialites = $pdo->query("SELECT DISTINCT metier FROM professionnels WHERE metier IS NOT NULL AND metier != '' ORDER BY metier")->fetchAll(PDO::FETCH_COLUMN);
$villes      = $pdo->query("SELECT DISTINCT ville  FROM professionnels WHERE ville  IS NOT NULL AND ville  != '' ORDER BY ville")->fetchAll(PDO::FETCH_COLUMN);

// Professionnel à éditer
$edit_pro = null;
$subAction = $_GET['action'] ?? '';
if ($subAction === 'edit' && isset($_GET['id']) && $canEdit) {
    $stmt = $pdo->prepare("SELECT * FROM professionnels WHERE id=?");
    $stmt->execute([(int)$_GET['id']]);
    $edit_pro = $stmt->fetch() ?: null;
}

// URL de base pour les liens (conserve page=professionnels)
$baseUrl = 'index.php?page=professionnels';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professionnels – GSB Visite</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem;">
        <h1>👨‍⚕️ Professionnels de santé</h1>
        <?php if ($canEdit): ?>
            <a href="<?= $baseUrl ?>&action=add" class="btn-add">+ Ajouter un professionnel</a>
        <?php endif; ?>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Formulaire Ajout -->
    <?php if ($subAction === 'add' && $canEdit): ?>
    <section class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2>➕ Ajouter un professionnel</h2>
        <form method="POST" action="<?= $baseUrl ?>">
            <input type="hidden" name="action" value="create">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                <div class="input-group"><label>Nom *</label><input type="text" name="nom" class="form-control" required></div>
                <div class="input-group"><label>Prénom *</label><input type="text" name="prenom" class="form-control" required></div>
                <div class="input-group"><label>Spécialité *</label><input type="text" name="metier" class="form-control" required></div>
                <div class="input-group"><label>Adresse</label><input type="text" name="adresse" class="form-control"></div>
                <div class="input-group"><label>Ville</label><input type="text" name="ville" class="form-control"></div>
                <div class="input-group"><label>Téléphone</label><input type="tel" name="telephone" class="form-control"></div>
                <div class="input-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
            </div>
            <div style="display:flex; gap:1rem; margin-top:1rem;">
                <button type="submit" class="btn">Ajouter</button>
                <a href="<?= $baseUrl ?>" class="btn btn-outline">Annuler</a>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <!-- Formulaire Édition -->
    <?php if ($edit_pro): ?>
    <section class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2>✏️ Modifier le professionnel</h2>
        <form method="POST" action="<?= $baseUrl ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="pro_id" value="<?= (int)$edit_pro['id'] ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                <div class="input-group"><label>Nom *</label><input type="text" name="nom" value="<?= htmlspecialchars($edit_pro['nom']) ?>" class="form-control" required></div>
                <div class="input-group"><label>Prénom *</label><input type="text" name="prenom" value="<?= htmlspecialchars($edit_pro['prenom']) ?>" class="form-control" required></div>
                <div class="input-group"><label>Spécialité *</label><input type="text" name="metier" value="<?= htmlspecialchars($edit_pro['metier']) ?>" class="form-control" required></div>
                <div class="input-group"><label>Adresse</label><input type="text" name="adresse" value="<?= htmlspecialchars($edit_pro['adresse'] ?? '') ?>" class="form-control"></div>
                <div class="input-group"><label>Ville</label><input type="text" name="ville" value="<?= htmlspecialchars($edit_pro['ville'] ?? '') ?>" class="form-control"></div>
                <div class="input-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= htmlspecialchars($edit_pro['telephone'] ?? '') ?>" class="form-control"></div>
                <div class="input-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($edit_pro['email'] ?? '') ?>" class="form-control"></div>
            </div>
            <div style="display:flex; gap:1rem; margin-top:1rem;">
                <button type="submit" class="btn">Enregistrer</button>
                <a href="<?= $baseUrl ?>" class="btn btn-outline">Annuler</a>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <!-- Recherche -->
    <section class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2>🔍 Recherche</h2>
        <form method="GET" action="index.php">
            <input type="hidden" name="page" value="professionnels">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                <div class="input-group">
                    <label>Nom / Prénom</label>
                    <input type="text" name="search_nom" value="<?= htmlspecialchars($search_nom) ?>" placeholder="Entrez un nom..." class="form-control">
                </div>
                <div class="input-group">
                    <label>Spécialité</label>
                    <select name="search_spec" class="form-control">
                        <option value="">-- Toutes les spécialités --</option>
                        <?php foreach ($specialites as $spec): ?>
                            <option value="<?= htmlspecialchars($spec) ?>" <?= $search_spec === $spec ? 'selected' : '' ?>><?= htmlspecialchars($spec) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>Ville</label>
                    <select name="search_ville" class="form-control">
                        <option value="">-- Toutes les villes --</option>
                        <?php foreach ($villes as $v): ?>
                            <option value="<?= htmlspecialchars($v) ?>" <?= $search_ville === $v ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display:flex; gap:1rem;">
                <button type="submit" class="btn">🔍 Rechercher</button>
                <a href="<?= $baseUrl ?>" class="btn btn-outline">Réinitialiser</a>
            </div>
        </form>
    </section>

    <!-- Tableau -->
    <section class="card" style="padding:1.5rem;">
        <h2>Liste (<?= $total ?>)</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Spécialité</th>
                    <th>Ville</th>
                    <th>Téléphone</th>
                    <th>Email</th>
                    <?php if ($canEdit): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($professionnels): ?>
                    <?php foreach ($professionnels as $pro): ?>
                    <tr>
                        <td><?= htmlspecialchars($pro['nom']) ?></td>
                        <td><?= htmlspecialchars($pro['prenom']) ?></td>
                        <td><span class="badge-metier"><?= htmlspecialchars($pro['metier']) ?></span></td>
                        <td><?= htmlspecialchars($pro['ville']) ?></td>
                        <td><?= htmlspecialchars($pro['telephone'] ?? '') ?></td>
                        <td><?= htmlspecialchars($pro['email'] ?? '') ?></td>
                        <?php if ($canEdit): ?>
                        <td>
                            <a href="<?= $baseUrl ?>&action=edit&id=<?= $pro['id'] ?>" class="btn-small btn-edit">✏️ Éditer</a>
                            <?php if ($canDelete): ?>
                            <form method="POST" action="<?= $baseUrl ?>" style="display:inline;" onsubmit="return confirm('Supprimer ce professionnel ?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="pro_id" value="<?= $pro['id'] ?>">
                                <button type="submit" class="btn-small btn-danger">🗑️</button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:2rem;">Aucun professionnel trouvé.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div style="display:flex; gap:.5rem; margin-top:1rem; justify-content:center;">
            <?php if ($pnum > 1): ?>
                <a href="<?= $baseUrl ?>&search_nom=<?= urlencode($search_nom) ?>&search_spec=<?= urlencode($search_spec) ?>&search_ville=<?= urlencode($search_ville) ?>&pnum=<?= $pnum-1 ?>" class="btn btn-outline">‹ Précédente</a>
            <?php endif; ?>
            <span style="padding:.5rem 1rem; background:var(--navy); color:white; border-radius:8px;"><?= $pnum ?> / <?= $pages ?></span>
            <?php if ($pnum < $pages): ?>
                <a href="<?= $baseUrl ?>&search_nom=<?= urlencode($search_nom) ?>&search_spec=<?= urlencode($search_spec) ?>&search_ville=<?= urlencode($search_ville) ?>&pnum=<?= $pnum+1 ?>" class="btn btn-outline">Suivante ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
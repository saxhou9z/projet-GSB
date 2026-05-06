<?php
require_once __DIR__ . '/config.php';
requireLogin();

$user = getCurrentUser();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Visiteur: only sees own RDV
// Délégué: sees RDV of his visiteurs
// Chef: sees all

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $prof_id  = $_POST['professionnel_id'] ?? '';
        $date_rdv = $_POST['date_rdv'] ?? '';
        $notes    = trim($_POST['notes'] ?? '');
        $med_ids  = $_POST['medicaments'] ?? [];

        // Determine whose RDV this is
        if ($role === 'visiteur') {
            $target_uid = $uid;
        } elseif ($role === 'delegue' && !empty($_POST['visiteur_id'])) {
            // Verify visiteur belongs to this delegue
            $chk = $pdo->prepare("SELECT id FROM utilisateurs WHERE id=? AND responsable_id=? AND role='visiteur'");
            $chk->execute([(int)$_POST['visiteur_id'], $uid]);
            $target_uid = $chk->fetchColumn() ?: $uid;
        } else {
            $target_uid = !empty($_POST['visiteur_id']) ? (int)$_POST['visiteur_id'] : $uid;
        }

        if (!empty($prof_id) && !empty($date_rdv)) {
            $stmt = $pdo->prepare("INSERT INTO rendez_vous (utilisateur_id, professionnel_id, date_rdv, notes) VALUES (?,?,?,?)");
            $stmt->execute([$target_uid, $prof_id, $date_rdv, $notes]);
            $rdv_id = $pdo->lastInsertId();
            foreach ($med_ids as $mid) {
                $pdo->prepare("INSERT INTO rdv_medicaments (rendez_vous_id, medicament_id) VALUES (?,?)")->execute([$rdv_id, $mid]);
            }
            $message = 'Rendez-vous créé avec succès'; $messageType = 'success';
        } else {
            $message = 'Champs obligatoires manquants'; $messageType = 'error';
        }

    } elseif ($action === 'valider') {
        $rdv_id       = (int)($_POST['rdv_id'] ?? 0);
        $compte_rendu = trim($_POST['compte_rendu'] ?? '');
        // Visiteur can validate own, delegue can validate his team's
        if ($role === 'visiteur') {
            $pdo->prepare("UPDATE rendez_vous SET statut='effectué', compte_rendu=? WHERE id=? AND utilisateur_id=?")->execute([$compte_rendu, $rdv_id, $uid]);
        } elseif ($role === 'delegue') {
            $pdo->prepare("UPDATE rendez_vous r SET r.statut='effectué', r.compte_rendu=? WHERE r.id=? AND EXISTS (SELECT 1 FROM utilisateurs u WHERE u.id=r.utilisateur_id AND u.responsable_id=?)")->execute([$compte_rendu, $rdv_id, $uid]);
        } else {
            $pdo->prepare("UPDATE rendez_vous SET statut='effectué', compte_rendu=? WHERE id=?")->execute([$compte_rendu, $rdv_id]);
        }
        $message = 'RDV validé'; $messageType = 'success';

    } elseif ($action === 'annuler') {
        $rdv_id = (int)($_POST['rdv_id'] ?? 0);
        if ($role === 'visiteur') {
            $pdo->prepare("UPDATE rendez_vous SET statut='annulé' WHERE id=? AND utilisateur_id=?")->execute([$rdv_id, $uid]);
        } elseif ($role === 'delegue') {
            $pdo->prepare("UPDATE rendez_vous r SET r.statut='annulé' WHERE r.id=? AND EXISTS (SELECT 1 FROM utilisateurs u WHERE u.id=r.utilisateur_id AND u.responsable_id=?)")->execute([$rdv_id, $uid]);
        } else {
            $pdo->prepare("UPDATE rendez_vous SET statut='annulé' WHERE id=?")->execute([$rdv_id]);
        }
        $message = 'RDV annulé'; $messageType = 'success';

    } elseif ($action === 'delete') {
        $rdv_id = (int)($_POST['rdv_id'] ?? 0);
        if ($role === 'visiteur') {
            $pdo->prepare("DELETE FROM rendez_vous WHERE id=? AND utilisateur_id=?")->execute([$rdv_id, $uid]);
        } elseif ($role === 'delegue') {
            $pdo->prepare("DELETE r FROM rendez_vous r JOIN utilisateurs u ON r.utilisateur_id=u.id WHERE r.id=? AND u.responsable_id=?")->execute([$rdv_id, $uid]);
        } else {
            $pdo->prepare("DELETE FROM rendez_vous WHERE id=?")->execute([$rdv_id]);
        }
        $message = 'RDV supprimé'; $messageType = 'success';
    }
}

// Filters
$search_prof   = trim($_GET['search_prof'] ?? '');
$filter_statut = $_GET['statut'] ?? '';
$filter_date   = $_GET['date'] ?? '';

$where = ['1=1'];
$params = [];

if ($role === 'visiteur') {
    $where[] = 'r.utilisateur_id = ?'; $params[] = $uid;
} elseif ($role === 'delegue') {
    $where[] = 'u.responsable_id = ?'; $params[] = $uid;
}
// chef: no restriction

if ($search_prof) {
    $where[] = "(p.nom LIKE ? OR p.prenom LIKE ? OR p.ville LIKE ?)";
    $params[] = "%$search_prof%"; $params[] = "%$search_prof%"; $params[] = "%$search_prof%";
}
if ($filter_statut) { $where[] = 'r.statut = ?'; $params[] = $filter_statut; }
if ($filter_date)   { $where[] = 'DATE(r.date_rdv) = ?'; $params[] = $filter_date; }

$sql = "
    SELECT r.*, p.nom as prof_nom, p.prenom as prof_prenom, p.metier, p.ville,
           u.nom as vis_nom, u.prenom as vis_prenom
    FROM rendez_vous r
    JOIN professionnels p ON r.professionnel_id = p.id
    JOIN utilisateurs u ON r.utilisateur_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.date_rdv DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rendezVous = $stmt->fetchAll();

$professionnels = $pdo->query("SELECT * FROM professionnels ORDER BY nom")->fetchAll();
$medicaments    = $pdo->query("SELECT m.*, f.libelle as famille FROM medicaments m LEFT JOIN familles f ON m.famille_id=f.id ORDER BY m.designation")->fetchAll();

// Visiteurs list for delegue/chef
$visiteurs = [];
if ($role === 'delegue') {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE responsable_id=? AND role='visiteur' ORDER BY nom");
    $stmt->execute([$uid]);
    $visiteurs = $stmt->fetchAll();
} elseif ($role === 'chef') {
    $visiteurs = $pdo->query("SELECT * FROM utilisateurs WHERE role='visiteur' ORDER BY nom")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rendez-vous – GSB Visite</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Formulaire ajout -->
    <section class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2>➕ Nouveau rendez-vous</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid-3">
                <?php if ($role !== 'visiteur'): ?>
                <div class="input-group">
                    <label>Visiteur *</label>
                    <select name="visiteur_id" class="form-control" required>
                        <option value="">-- Choisir un visiteur --</option>
                        <?php foreach ($visiteurs as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['prenom'].' '.$v['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="input-group">
                    <label>Professionnel de santé *</label>
                    <select name="professionnel_id" required class="form-control">
                        <option value="">-- Choisir --</option>
                        <?php foreach ($professionnels as $pro): ?>
                            <option value="<?= $pro['id'] ?>">Dr. <?= htmlspecialchars($pro['nom'].' '.$pro['prenom']) ?> (<?= htmlspecialchars($pro['metier']) ?> – <?= htmlspecialchars($pro['ville']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label>Date et heure *</label>
                    <input type="datetime-local" name="date_rdv" required class="form-control">
                </div>
                <div class="input-group">
                    <label>Notes</label>
                    <input type="text" name="notes" placeholder="Objectif de la visite..." class="form-control">
                </div>
            </div>
            <div class="input-group">
                <label>Médicaments à présenter</label>
                <div class="checkbox-grid">
                    <?php foreach ($medicaments as $med): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="medicaments[]" value="<?= $med['id'] ?>">
                            <span><?= htmlspecialchars($med['designation']) ?></span>
                            <small class="famille-tag"><?= htmlspecialchars($med['famille'] ?? '') ?></small>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn-add">➕ Créer le rendez-vous</button>
        </form>
    </section>

    <!-- Filtres -->
    <section class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2>🔍 Filtres</h2>
        <form method="GET" action="index.php">
            <input type="hidden" name="page" value="rendez_vous">
            <div class="form-grid-4">
                <div class="input-group">
                    <label>Professionnel / Ville</label>
                    <input type="text" name="search_prof" value="<?= htmlspecialchars($search_prof) ?>" placeholder="Nom, ville..." class="form-control">
                </div>
                <div class="input-group">
                    <label>Statut</label>
                    <select name="statut" class="form-control">
                        <option value="">Tous</option>
                        <option value="planifié"  <?= $filter_statut==='planifié'?'selected':'' ?>>Planifié</option>
                        <option value="effectué"  <?= $filter_statut==='effectué'?'selected':'' ?>>Effectué</option>
                        <option value="annulé"    <?= $filter_statut==='annulé'?'selected':'' ?>>Annulé</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="form-control">
                </div>
                <div class="input-group" style="display:flex; align-items:flex-end; gap:0.5rem;">
                    <button type="submit" class="btn" style="flex:1;">Filtrer</button>
                    <a href="index.php?page=rendez_vous" class="btn btn-outline" style="flex:1; text-align:center;">Reset</a>
                </div>
            </div>
        </form>
    </section>

    <!-- Liste -->
    <section class="card" style="padding:1.5rem;">
        <h2>📋 Rendez-vous (<?= count($rendezVous) ?>)</h2>
        <?php if (empty($rendezVous)): ?>
            <p style="color:var(--text-muted); text-align:center; padding:2rem;">Aucun rendez-vous trouvé.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <?php if ($role !== 'visiteur'): ?><th>Visiteur</th><?php endif; ?>
                        <th>Professionnel</th>
                        <th>Spécialité</th>
                        <th>Ville</th>
                        <th>Notes</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rendezVous as $rdv): ?>
                    <tr>
                        <td><strong><?= date('d/m/Y', strtotime($rdv['date_rdv'])) ?></strong><br><small><?= date('H:i', strtotime($rdv['date_rdv'])) ?></small></td>
                        <?php if ($role !== 'visiteur'): ?>
                            <td><?= htmlspecialchars($rdv['vis_prenom'].' '.$rdv['vis_nom']) ?></td>
                        <?php endif; ?>
                        <td>Dr. <?= htmlspecialchars($rdv['prof_nom'].' '.$rdv['prof_prenom']) ?></td>
                        <td><span class="badge-metier"><?= htmlspecialchars($rdv['metier']) ?></span></td>
                        <td><?= htmlspecialchars($rdv['ville']) ?></td>
                        <td><?= htmlspecialchars($rdv['notes'] ?? '') ?></td>
                        <td><span class="badge badge-<?= $rdv['statut'] ?>"><?= $rdv['statut'] ?></span></td>
                        <td>
                            <?php if ($rdv['statut'] === 'planifié'): ?>
                                <button class="btn-small btn-edit" onclick="openValiderModal(<?= $rdv['id'] ?>)">✓ Valider</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Annuler ce RDV ?')">
                                    <input type="hidden" name="action" value="annuler">
                                    <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                    <button type="submit" class="btn-small" style="background:#d4882a;color:white;">✕ Annuler</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                <button type="submit" class="btn-small btn-danger">🗑</button>
                            </form>
                            <?php if ($rdv['compte_rendu']): ?>
                                <button class="btn-small btn-outline-sm" onclick="alert('<?= htmlspecialchars(addslashes($rdv['compte_rendu'])) ?>')">📄 CR</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<div id="validerModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; padding:2rem; border-radius:16px; max-width:480px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <h2 style="margin-bottom:1rem; font-family:'Playfair Display',serif; color:#1a3a5c;">✓ Valider le rendez-vous</h2>
        <form method="POST">
            <input type="hidden" name="action" value="valider">
            <input type="hidden" name="rdv_id" id="valider_id">
            <div class="input-group">
                <label>Compte-rendu de visite</label>
                <textarea name="compte_rendu" rows="4" class="form-control" placeholder="Décrivez le déroulement de la visite..."></textarea>
            </div>
            <div style="display:flex; gap:1rem; margin-top:1rem;">
                <button type="submit" class="btn" style="flex:1;">✓ Confirmer</button>
                <button type="button" onclick="document.getElementById('validerModal').style.display='none'" class="btn btn-outline" style="flex:1;">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
function openValiderModal(id) {
    document.getElementById('valider_id').value = id;
    document.getElementById('validerModal').style.display = 'flex';
}
window.onclick = e => { if(e.target.id==='validerModal') e.target.style.display='none'; }
</script>
</body>
</html>

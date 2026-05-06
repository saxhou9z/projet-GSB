<?php
require_once __DIR__ . '/config.php';

$page = $_GET['page'] ?? 'dashboard';

if (!in_array($page, ['login', 'logout'])) {
    requireLogin();
}

$allowed = ['login','logout','rendez_vous','medicaments','professionnels','gestion_visiteurs','gestion_equipe','historique'];
if (in_array($page, $allowed)) {
    $file = __DIR__ . '/' . $page . '.php';
    if (file_exists($file)) { require_once $file; exit; }
}

if ($page === '403') {
    http_response_code(403);
    echo "<h1>Accès refusé</h1><a href='index.php'>Retour</a>"; exit;
}

// ── Dashboard ─────────────────────────────────────────────
$user = getCurrentUser();
$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'visiteur';

if ($role === 'chef') {
    $nbVisiteurs      = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='visiteur'")->fetchColumn();
    $nbDelegues       = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='delegue'")->fetchColumn();
    $nbProfessionnels = $pdo->query("SELECT COUNT(*) FROM professionnels")->fetchColumn();
    $nbMedicaments    = $pdo->query("SELECT COUNT(*) FROM medicaments")->fetchColumn();
    $nbRdvTotal       = $pdo->query("SELECT COUNT(*) FROM rendez_vous")->fetchColumn();
    $nbRdvEffectues   = $pdo->query("SELECT COUNT(*) FROM rendez_vous WHERE statut='effectué'")->fetchColumn();
    $tauxGlobal       = $nbRdvTotal > 0 ? round($nbRdvEffectues / $nbRdvTotal * 100) : 0;
    $rdvAVenir = $pdo->query("
        SELECT r.*, p.nom as prof_nom, p.prenom as prof_prenom, p.metier,
               u.nom as vis_nom, u.prenom as vis_prenom
        FROM rendez_vous r
        JOIN professionnels p ON r.professionnel_id = p.id
        JOIN utilisateurs u ON r.utilisateur_id = u.id
        WHERE r.statut='planifié' AND r.date_rdv >= NOW()
        ORDER BY r.date_rdv ASC LIMIT 8
    ")->fetchAll();

} elseif ($role === 'delegue') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE responsable_id=? AND role='visiteur'");
    $stmt->execute([$uid]); $nbVisiteurs = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous r JOIN utilisateurs u ON r.utilisateur_id=u.id WHERE u.responsable_id=? AND DATE(r.date_rdv)=CURDATE()");
    $stmt->execute([$uid]); $nbRdvAujourdhui = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous r JOIN utilisateurs u ON r.utilisateur_id=u.id WHERE u.responsable_id=? AND r.statut='effectué'");
    $stmt->execute([$uid]); $nbEffectues = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous r JOIN utilisateurs u ON r.utilisateur_id=u.id WHERE u.responsable_id=?");
    $stmt->execute([$uid]); $nbTotal = $stmt->fetchColumn();
    $tauxVisite = $nbTotal > 0 ? round($nbEffectues/$nbTotal*100) : 0;

    $stmt = $pdo->prepare("
        SELECT r.*, p.nom as prof_nom, p.prenom as prof_prenom, p.metier,
               u.nom as vis_nom, u.prenom as vis_prenom
        FROM rendez_vous r
        JOIN professionnels p ON r.professionnel_id=p.id
        JOIN utilisateurs u ON r.utilisateur_id=u.id
        WHERE u.responsable_id=? AND r.statut='planifié' AND r.date_rdv>=NOW()
        ORDER BY r.date_rdv ASC LIMIT 8
    ");
    $stmt->execute([$uid]); $rdvAVenir = $stmt->fetchAll();

} else {
    // visiteur
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE utilisateur_id=? AND statut='planifié'");
    $stmt->execute([$uid]); $nbPlanifies = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE utilisateur_id=? AND DATE(date_rdv)=CURDATE()");
    $stmt->execute([$uid]); $nbRdvAujourdhui = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE utilisateur_id=? AND statut='effectué'");
    $stmt->execute([$uid]); $nbEffectues = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE utilisateur_id=?");
    $stmt->execute([$uid]); $nbTotal = $stmt->fetchColumn();
    $tauxVisite = $nbTotal > 0 ? round($nbEffectues/$nbTotal*100) : 0;

    $stmt = $pdo->prepare("
        SELECT r.*, p.nom as prof_nom, p.prenom as prof_prenom, p.metier
        FROM rendez_vous r
        JOIN professionnels p ON r.professionnel_id=p.id
        WHERE r.utilisateur_id=? AND r.statut='planifié' AND r.date_rdv>=NOW()
        ORDER BY r.date_rdv ASC LIMIT 5
    ");
    $stmt->execute([$uid]); $rdvAVenir = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT r.*, p.nom as prof_nom, p.prenom as prof_prenom, p.metier
        FROM rendez_vous r
        JOIN professionnels p ON r.professionnel_id=p.id
        WHERE r.utilisateur_id=? AND DATE(r.date_rdv)=CURDATE()
        ORDER BY r.date_rdv ASC
    ");
    $stmt->execute([$uid]); $rdvAujourdhui = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – GSB Visite</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/header.php'; ?>

    <div class="stats-grid">
        <?php if ($role === 'chef'): ?>
            <div class="stat-card"><h3>Délégués</h3><div class="value"><?= $nbDelegues ?></div></div>
            <div class="stat-card"><h3>Visiteurs</h3><div class="value"><?= $nbVisiteurs ?></div></div>
            <div class="stat-card"><h3>Professionnels</h3><div class="value"><?= $nbProfessionnels ?></div></div>
            <div class="stat-card"><h3>Médicaments</h3><div class="value"><?= $nbMedicaments ?></div></div>
            <div class="stat-card"><h3>RDV total</h3><div class="value"><?= $nbRdvTotal ?></div></div>
            <div class="stat-card"><h3>Taux global</h3><div class="value"><?= $tauxGlobal ?>%</div></div>
        <?php elseif ($role === 'delegue'): ?>
            <div class="stat-card"><h3>Mes visiteurs</h3><div class="value"><?= $nbVisiteurs ?></div></div>
            <div class="stat-card"><h3>RDV aujourd'hui</h3><div class="value"><?= $nbRdvAujourdhui ?></div></div>
            <div class="stat-card"><h3>RDV effectués</h3><div class="value"><?= $nbEffectues ?></div></div>
            <div class="stat-card"><h3>Taux de visite</h3><div class="value"><?= $tauxVisite ?>%</div></div>
        <?php else: ?>
            <div class="stat-card"><h3>RDV aujourd'hui</h3><div class="value"><?= $nbRdvAujourdhui ?></div></div>
            <div class="stat-card"><h3>RDV planifiés</h3><div class="value"><?= $nbPlanifies ?></div></div>
            <div class="stat-card"><h3>RDV effectués</h3><div class="value"><?= $nbEffectues ?></div></div>
            <div class="stat-card"><h3>Taux de visite</h3><div class="value"><?= $tauxVisite ?>%</div></div>
        <?php endif; ?>
    </div>

    <main>
        <?php if ($role === 'visiteur' && !empty($rdvAujourdhui)): ?>
        <section style="margin-bottom:1.5rem; border-left: 4px solid var(--gold);">
            <h2>📅 Aujourd'hui</h2>
            <div class="agenda">
                <?php foreach ($rdvAujourdhui as $rdv): ?>
                    <div class="event event-today">
                        <strong><?= date('H:i', strtotime($rdv['date_rdv'])) ?></strong>
                        — Dr. <?= htmlspecialchars($rdv['prof_nom'].' '.$rdv['prof_prenom']) ?>
                        <span class="badge-metier"><?= htmlspecialchars($rdv['metier']) ?></span>
                        <?php if ($rdv['notes']): ?>
                            <div class="event-notes"><?= htmlspecialchars($rdv['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section>
            <h2>
                <?= $role === 'chef' ? '📋 Prochains RDV (tous visiteurs)' : ($role === 'delegue' ? '📋 Prochains RDV de mon équipe' : '📋 Mes prochains rendez-vous') ?>
            </h2>
            <div class="agenda">
                <?php if (!empty($rdvAVenir)): ?>
                    <?php foreach ($rdvAVenir as $rdv): ?>
                        <div class="event">
                            <strong><?= date('d/m/Y H:i', strtotime($rdv['date_rdv'])) ?></strong>
                            <?php if ($role !== 'visiteur'): ?>
                                — <em><?= htmlspecialchars($rdv['vis_prenom'].' '.$rdv['vis_nom']) ?></em> →
                            <?php else: ?>—<?php endif; ?>
                            Dr. <?= htmlspecialchars($rdv['prof_nom'].' '.$rdv['prof_prenom']) ?>
                            <span class="badge-metier"><?= htmlspecialchars($rdv['metier']) ?></span>
                            <?php if ($rdv['notes']): ?>
                                <div class="event-notes"><?= htmlspecialchars($rdv['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--text-muted); padding:1rem 0;">Aucun rendez-vous à venir.</p>
                <?php endif; ?>
            </div>

            <div class="dashboard-actions">
                <?php if ($role === 'visiteur'): ?>

                <?php elseif ($role === 'delegue'): ?>
                    <a href="index.php?page=rendez_vous" class="btn">📅 RDV de l'équipe</a>
                    <a href="index.php?page=gestion_visiteurs" class="btn btn-outline">👥 Gérer mes visiteurs</a>
                <?php elseif ($role === 'chef'): ?>
                    <a href="index.php?page=gestion_equipe" class="btn">⚙️ Gérer l'équipe</a>
                    <a href="index.php?page=rendez_vous" class="btn btn-outline">📅 Tous les RDV</a>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>

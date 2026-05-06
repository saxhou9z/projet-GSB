<?php
$user = getCurrentUser();
$role = $_SESSION['user_role'] ?? 'visiteur';
$currentPage = $_GET['page'] ?? 'dashboard';
?>
<div class="topbar">
    <div class="topbar-inner">
        <a href="index.php" class="topbar-brand">
            <img src="logo.png" alt="GSB" class="topbar-logo">
            <span class="topbar-brand-name">GSB <em>Visite</em></span>
        </a>

        <nav class="topbar-nav">
            <a href="index.php" class="topbar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">🏠 Dashboard</a>

            <?php if ($role === 'visiteur'): ?>
                <a href="index.php?page=rendez_vous" class="topbar-link <?= $currentPage === 'rendez_vous' ? 'active' : '' ?>">📅 Mes RDV</a>
                <a href="index.php?page=medicaments" class="topbar-link <?= $currentPage === 'medicaments' ? 'active' : '' ?>">💊 Médicaments</a>
            <?php endif; ?>

            <?php if ($role === 'delegue'): ?>
                <a href="index.php?page=rendez_vous" class="topbar-link <?= $currentPage === 'rendez_vous' ? 'active' : '' ?>">📅 RDV équipe</a>
                <a href="index.php?page=gestion_visiteurs" class="topbar-link <?= $currentPage === 'gestion_visiteurs' ? 'active' : '' ?>">👥 Mes visiteurs</a>
                <a href="index.php?page=medicaments" class="topbar-link <?= $currentPage === 'medicaments' ? 'active' : '' ?>">💊 Médicaments</a>
                <a href="index.php?page=professionnels" class="topbar-link <?= $currentPage === 'professionnels' ? 'active' : '' ?>">👨‍⚕️ Professionnels</a>
            <?php endif; ?>

            <?php if ($role === 'chef'): ?>
                <a href="index.php?page=rendez_vous" class="topbar-link <?= $currentPage === 'rendez_vous' ? 'active' : '' ?>">📅 Tous les RDV</a>
                <a href="index.php?page=medicaments" class="topbar-link <?= $currentPage === 'medicaments' ? 'active' : '' ?>">💊 Médicaments</a>
                <a href="index.php?page=professionnels" class="topbar-link <?= $currentPage === 'professionnels' ? 'active' : '' ?>">👨‍⚕️ Professionnels</a>
                <a href="index.php?page=gestion_equipe" class="topbar-link <?= $currentPage === 'gestion_equipe' ? 'active' : '' ?>">⚙️ Gestion équipe</a>
            <?php endif; ?>
        </nav>

        <div class="topbar-user">
            <div class="topbar-avatar">
                <?= strtoupper(substr($user['prenom'] ?? 'U', 0, 1) . substr($user['nom'] ?? 'N', 0, 1)) ?>
            </div>
            <div class="topbar-user-info">
                <span class="topbar-user-name"><?= htmlspecialchars($user['prenom'] ?? '') ?> <?= htmlspecialchars($user['nom'] ?? '') ?></span>
                <span class="topbar-user-role"><?= $role === 'chef' ? 'Chef régional' : ($role === 'delegue' ? 'Délégué régional' : 'Visiteur médical') ?></span>
            </div>
            <a href="index.php?page=logout" class="topbar-logout" title="Déconnexion">🚪</a>
        </div>
    </div>
</div>

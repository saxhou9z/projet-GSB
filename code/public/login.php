<?php
require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion – GSB Visite</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-wrap">

        <!-- Panneau gauche -->
        <div class="login-left">
            <div class="left-content">
                <div class="logo-circle">
                    <img src="logo.png" alt="GSB Visite">
                </div>
                <div class="left-title">GSB<br><span>Gestion des Visite</span></div>
                <div class="left-divider"></div>
                <div class="left-features">
                    <div class="feature-item">
                        <span>Gestion des rendez-vous médicaux</span>
                    </div>
                    <div class="feature-item">
                        <span>Catalogue des médicaments</span>
                    </div>
                    <div class="feature-item">
                        <span>Suivi des visites et statistiques</span>
                    </div>
                    <div class="feature-item">
                        <span>Annuaire des professionnels de santé</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panneau droit -->
        <div class="login-right">
            <div class="login-form-wrap">

                <div class="form-header">
                    <h1>Connexion</h1>
                    <div class="gold-bar"></div>
                    <p>Accédez à votre espace</p>
                </div>

                <?php if ($error): ?>
                    <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group">
                        <label>Adresse email</label>
                        <div class="input-wrap">
                            <span class="input-icon"></span>
                            <input type="email" name="email"
                                   placeholder="jean.dupont@exemple.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   required autofocus>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Mot de passe</label>
                        <div class="input-wrap">
                            <span class="input-icon"></span>
                            <input type="password" name="password"
                                   placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Se connecter →</button>
                </form>

                <div class="login-footer">
                    <p>© 2025 – Application GSB Visite</p>
                </div>

            </div>
        </div>

    </div>
</body>
</html>
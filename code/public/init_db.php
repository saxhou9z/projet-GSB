<?php
// Script d'initialisation de la base de données
// À exécuter une seule fois après le démarrage de Docker

$host = 'db';
$dbname = 'visiteur_medical';
$user = 'visiteur';
$pass = 'visiteur123';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connexion à MySQL réussie<br>";
    
    // Créer la base de données si elle n'existe pas
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");
    
    echo "✓ Base de données créée/sélectionnée<br>";
    
    // Créer les tables
    $sql = "
    -- Table des utilisateurs
    CREATE TABLE IF NOT EXISTS utilisateurs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        mot_de_passe VARCHAR(255) NOT NULL,
        role ENUM('visiteur', 'admin') DEFAULT 'visiteur',
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Table des professionnels de santé
    CREATE TABLE IF NOT EXISTS professionnels (
        id INT PRIMARY KEY AUTO_INCREMENT,
        code VARCHAR(20) UNIQUE NOT NULL,
        nom VARCHAR(100) NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        metier VARCHAR(100) NOT NULL,
        ville VARCHAR(100) NOT NULL,
        adresse TEXT,
        telephone VARCHAR(20),
        email VARCHAR(150),
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Table des médicaments
    CREATE TABLE IF NOT EXISTS medicaments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        code VARCHAR(20) UNIQUE NOT NULL,
        designation VARCHAR(200) NOT NULL,
        prix DECIMAL(10,2) NOT NULL,
        description TEXT,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Table des rendez-vous
    CREATE TABLE IF NOT EXISTS rendez_vous (
        id INT PRIMARY KEY AUTO_INCREMENT,
        utilisateur_id INT NOT NULL,
        professionnel_id INT NOT NULL,
        date_rdv DATETIME NOT NULL,
        statut ENUM('planifié', 'effectué', 'annulé') DEFAULT 'planifié',
        notes TEXT,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
        FOREIGN KEY (professionnel_id) REFERENCES professionnels(id) ON DELETE CASCADE
    );

    -- Table de liaison rendez-vous et médicaments
    CREATE TABLE IF NOT EXISTS rdv_medicaments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        rendez_vous_id INT NOT NULL,
        medicament_id INT NOT NULL,
        quantite_presentee INT DEFAULT 1,
        FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous(id) ON DELETE CASCADE,
        FOREIGN KEY (medicament_id) REFERENCES medicaments(id) ON DELETE CASCADE
    );
    ";
    
    $pdo->exec($sql);
    echo "✓ Tables créées<br>";
    
    // Vérifier si des données existent déjà
    $stmt = $pdo->query("SELECT COUNT(*) FROM utilisateurs");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Insertion des données de test
        $pdo->exec("
        INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role) VALUES
        ('Dupont', 'Jean', 'jean.dupont@mail.fr', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'visiteur'),
        ('Admin', 'Super', 'admin@mail.fr', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
        ");
        
        $pdo->exec("
        INSERT INTO professionnels (code, nom, prenom, metier, ville, adresse, telephone) VALUES
        ('PDS001', 'Martin', 'Paul', 'Cardiologue', 'Paris', '15 Avenue des Champs', '0145678901'),
        ('PDS002', 'Leclerc', 'Claire', 'Dermatologue', 'Lyon', '8 Rue de la République', '0478901234'),
        ('PDS003', 'Bernard', 'Marie', 'Généraliste', 'Marseille', '22 Boulevard Longchamp', '0491234567');
        ");
        
        $pdo->exec("
        INSERT INTO medicaments (code, designation, prix, description) VALUES
        ('MED001', 'Paracétamol 500mg', 3.50, 'Antalgique et antipyrétique'),
        ('MED002', 'Ibuprofène 200mg', 4.20, 'Anti-inflammatoire non stéroïdien'),
        ('MED003', 'Amoxicilline 500mg', 7.10, 'Antibiotique à large spectre');
        ");
        
        $pdo->exec("
        INSERT INTO rendez_vous (utilisateur_id, professionnel_id, date_rdv, statut, notes) VALUES
        (1, 1, '2025-11-21 09:00:00', 'planifié', 'Présentation nouveaux produits cardio'),
        (1, 3, '2025-11-22 14:00:00', 'planifié', 'Suivi de visite'),
        (1, 2, '2025-11-23 11:00:00', 'planifié', 'Rencontre découverte');
        ");
        
        $pdo->exec("
        INSERT INTO rdv_medicaments (rendez_vous_id, medicament_id, quantite_presentee) VALUES
        (1, 1, 5),
        (1, 2, 3),
        (2, 3, 2);
        ");
        
        echo "✓ Données de test insérées<br>";
    } else {
        echo "✓ Données déjà présentes (pas de duplication)<br>";
    }
    
    echo "<br><strong style='color: green;'>✓ INITIALISATION TERMINÉE AVEC SUCCÈS!</strong><br>";
    echo "<br>Vous pouvez maintenant vous connecter avec:<br>";
    echo "Email: <strong>jean.dupont@mail.fr</strong><br>";
    echo "Mot de passe: <strong>password</strong><br>";
    echo "<br><a href='login.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px;'>Aller à la page de connexion</a>";
    
} catch(PDOException $e) {
    echo "<strong style='color: red;'>✗ ERREUR:</strong> " . $e->getMessage();
}
?>
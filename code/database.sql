-- ============================================================
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

--  GSB Visite – Base de données complète
-- ============================================================
CREATE DATABASE IF NOT EXISTS visiteur_medical CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE visiteur_medical;

-- ── 1. RÉGIONS ──────────────────────────────────────────────
CREATE TABLE regions (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    nom  VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- ── 2. UTILISATEURS ─────────────────────────────────────────
CREATE TABLE utilisateurs (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nom            VARCHAR(50)  NOT NULL,
    prenom         VARCHAR(50)  NOT NULL,
    email          VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe   VARCHAR(255) NOT NULL,
    role           ENUM('visiteur','delegue','chef') DEFAULT 'visiteur',
    region_id      INT NULL,
    responsable_id INT NULL,
    actif          TINYINT(1) DEFAULT 1,
    cree_le        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (region_id)      REFERENCES regions(id)      ON DELETE SET NULL,
    FOREIGN KEY (responsable_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 3. FAMILLES DE MÉDICAMENTS ──────────────────────────────
CREATE TABLE familles (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    code    VARCHAR(10)  NOT NULL UNIQUE,
    libelle VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- ── 4. MÉDICAMENTS ──────────────────────────────────────────
CREATE TABLE medicaments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(10)  NOT NULL UNIQUE,
    designation VARCHAR(255) NOT NULL,
    famille_id  INT NULL,
    prix        DECIMAL(10,2) NOT NULL,
    description TEXT,
    cree_le     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (famille_id) REFERENCES familles(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 5. PROFESSIONNELS DE SANTÉ ──────────────────────────────
CREATE TABLE professionnels (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    code      VARCHAR(10)  NOT NULL UNIQUE,
    nom       VARCHAR(50)  NOT NULL,
    prenom    VARCHAR(50)  NOT NULL,
    metier    VARCHAR(100) NOT NULL,
    ville     VARCHAR(100) NOT NULL,
    adresse   TEXT,
    telephone VARCHAR(20),
    email     VARCHAR(150),
    region_id INT NULL,
    FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 6. RENDEZ-VOUS ──────────────────────────────────────────
CREATE TABLE rendez_vous (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id   INT NOT NULL,
    professionnel_id INT NOT NULL,
    date_rdv         DATETIME NOT NULL,
    statut           ENUM('planifié','effectué','annulé') DEFAULT 'planifié',
    notes            TEXT,
    compte_rendu     TEXT,
    cree_le          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id)   REFERENCES utilisateurs(id)   ON DELETE CASCADE,
    FOREIGN KEY (professionnel_id) REFERENCES professionnels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 7. MÉDICAMENTS PRÉSENTÉS EN RDV ─────────────────────────
CREATE TABLE rdv_medicaments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    rendez_vous_id INT NOT NULL,
    medicament_id  INT NOT NULL,
    quantite       INT DEFAULT 1,
    FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous(id)  ON DELETE CASCADE,
    FOREIGN KEY (medicament_id)  REFERENCES medicaments(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  DONNÉES
-- ============================================================

INSERT INTO regions (nom) VALUES
('Île-de-France'),
('Auvergne-Rhône-Alpes'),
('Provence-Alpes-Côte d''Azur'),
('Occitanie'),
('Nouvelle-Aquitaine');

INSERT INTO familles (code, libelle) VALUES
('ANTIDOU', 'Antidouleurs'),
('ANTIINF', 'Anti-inflammatoires'),
('ANTIBIO', 'Antibiotiques'),
('CARDIO',  'Cardiologie'),
('DERMATO', 'Dermatologie'),
('GASTRO',  'Gastro-entérologie'),
('PNEUMO',  'Pneumologie'),
('NEURO',   'Neurologie');

-- ============================================================
--  UTILISATEURS
--  chef     → chef123
--  delegue  → delegue123
--  visiteur → visiteur123
-- ============================================================

-- 2 Chefs (id 1 et 2)
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, region_id, responsable_id) VALUES
('Rousseau', 'Marc',     'marc.rousseau@gsb.fr',    '$2b$10$XMsw2rhiAjFQ9oPu7yJWJelgp4OysazCyHNKTq4andQZXCyoW5p9e', 'chef', NULL, NULL),
('Fontaine', 'Isabelle', 'isabelle.fontaine@gsb.fr', '$2b$10$XMsw2rhiAjFQ9oPu7yJWJelgp4OysazCyHNKTq4andQZXCyoW5p9e', 'chef', NULL, NULL);

-- 4 Délégués (id 3, 4, 5, 6)
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, region_id, responsable_id) VALUES
('Laurent',   'Pierre', 'p.laurent@gsb.fr',   '$2b$10$UpuWN20PuuT4iqGVl3Hhh.4303/U4/e1Td.PIlpDZ0RSMcwiwB59m', 'delegue', 1, NULL),
('Morel',     'Sophie', 's.morel@gsb.fr',     '$2b$10$UpuWN20PuuT4iqGVl3Hhh.4303/U4/e1Td.PIlpDZ0RSMcwiwB59m', 'delegue', 2, NULL),
('Garnier',   'Thomas', 't.garnier@gsb.fr',   '$2b$10$UpuWN20PuuT4iqGVl3Hhh.4303/U4/e1Td.PIlpDZ0RSMcwiwB59m', 'delegue', 3, NULL),
('Chevalier', 'Lucie',  'l.chevalier@gsb.fr', '$2b$10$UpuWN20PuuT4iqGVl3Hhh.4303/U4/e1Td.PIlpDZ0RSMcwiwB59m', 'delegue', 4, NULL);

-- 8 Visiteurs (id 7 à 14), 2 par délégué
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, region_id, responsable_id) VALUES
('Dupont',  'Jean',    'jean.dupont@gsb.fr',    '$2b$10$fS/c33NgTb/IsERUyh7Oge39cOSjtL8FiSICKSEIPwOTyP9CuaBQe', 'visiteur', 1, 3),
('Martin',  'Claire',  'claire.martin@gsb.fr',  '$2b$10$fS/c33NgTb/IsERUyh7Oge39cOSjtL8FiSICKSEIPwOTyP9CuaBQe', 'visiteur', 1, 3),
('Bernard', 'Lucas',   'lucas.bernard@gsb.fr',  '$2b$10$fS/c33NgTb/IsERUyh7Oge39cOSjtL8FiSICKSEIPwOTyP9CuaBQe', 'visiteur', 2, 4),
('Petit',   'Emma',    'emma.petit@gsb.fr',     '$2b$10$fS/c33NgTb/IsERUyh7Oge39cOSjtL8FiSICKSEIPwOTyP9CuaBQe', 'visiteur', 2, 4),
('Leroy',   'Antoine', 'antoine.leroy@gsb.fr',  '$2b$10$fS/c33NgTb/IsERUyh7Oge39cOSjtL8FiSICKSEIPwOTyP9CuaBQe', 'visiteur', 3, 5),
('Simon',   'Camille', 'camille.simon@gsb.fr',  '$2b$10$fS/c33NgTb/IsERUyh7Oge39cOSjtL8FiSICKSEIPwOTyP9CuaBQe', 'visiteur', 3, 5),
('Michel',  'Julie',   'julie.michel@gsb.fr',   '$2b$10$fS/c33NgTb/IsERUyh7Oge39cOSjtL8FiSICKSEIPwOTyP9CuaBQe', 'visiteur', 4, 6),
('Robert',  'Nicolas', 'nicolas.robert@gsb.fr', '$2b$10$fS/c33NgTb/IsERUyh7Oge39cOSjtL8FiSICKSEIPwOTyP9CuaBQe', 'visiteur', 4, 6);

-- Professionnels de santé
INSERT INTO professionnels (code, nom, prenom, metier, ville, adresse, telephone, region_id) VALUES
('PDS001', 'Martin',  'Alice',  'Médecin Généraliste', 'Paris',     '15 Av. des Champs-Élysées', '0145678901', 1),
('PDS002', 'Bernard', 'Lucas',  'Cardiologue',          'Paris',     '8 Rue de Rivoli',           '0145123456', 1),
('PDS003', 'Dubois',  'Emma',   'Dermatologue',         'Lyon',      '22 Rue de la République',   '0478901234', 2),
('PDS004', 'Leroy',   'Marc',   'Pneumologue',          'Lyon',      '5 Pl. Bellecour',           '0478456789', 2),
('PDS005', 'Moreau',  'Sophie', 'Gastro-entérologue',   'Marseille', '12 Bd Longchamp',           '0491234567', 3),
('PDS006', 'Simon',   'Paul',   'Neurologue',           'Toulouse',  '3 Rue du Taur',             '0561234567', 4),
('PDS007', 'Michel',  'Anne',   'Médecin Généraliste',  'Bordeaux',  '7 Cours de l''Intendance',  '0556789012', 5);

-- Médicaments
INSERT INTO medicaments (code, designation, famille_id, prix, description) VALUES
('MED001', 'Paracétamol 500mg',   1,  3.50, 'Antalgique et antipyrétique de référence'),
('MED002', 'Paracétamol 1000mg',  1,  4.20, 'Antalgique forte dose'),
('MED003', 'Ibuprofène 200mg',    2,  4.80, 'Anti-inflammatoire non stéroïdien'),
('MED004', 'Ibuprofène 400mg',    2,  5.90, 'Anti-inflammatoire dose adulte'),
('MED005', 'Amoxicilline 500mg',  3,  7.10, 'Antibiotique pénicilline à large spectre'),
('MED006', 'Amoxicilline 1g',     3,  9.50, 'Antibiotique forte dose'),
('MED007', 'Azithromycine 250mg', 3, 12.80, 'Antibiotique macrolide'),
('MED008', 'Amlodipine 5mg',      4,  8.40, 'Inhibiteur calcique antihypertenseur'),
('MED009', 'Ramipril 5mg',        4,  6.70, 'IEC antihypertenseur'),
('MED010', 'Bétaméthasone crème', 5, 11.20, 'Corticoïde topique dermato'),
('MED011', 'Oméprazole 20mg',     6,  5.30, 'Inhibiteur de la pompe à protons'),
('MED012', 'Salbutamol spray',    7,  9.80, 'Bronchodilatateur bêta-2 agoniste'),
('MED013', 'Sertraline 50mg',     8, 14.60, 'Antidépresseur ISRS');

-- Rendez-vous (visiteurs id 7 à 14)
INSERT INTO rendez_vous (utilisateur_id, professionnel_id, date_rdv, statut, notes, compte_rendu) VALUES
(7,  1, DATE_ADD(NOW(), INTERVAL 1  DAY), 'planifié', 'Présentation Paracétamol 1g',    NULL),
(7,  2, DATE_ADD(NOW(), INTERVAL 3  DAY), 'planifié', 'Suivi cardio nouveaux produits', NULL),
(7,  3, DATE_SUB(NOW(), INTERVAL 2  DAY), 'effectué', 'Visite découverte',              'Médecin réceptif, intéressé par MED010'),
(8,  4, DATE_SUB(NOW(), INTERVAL 5  DAY), 'effectué', 'Présentation Salbutamol',        'Commande de 20 unités prévue'),
(8,  1, DATE_SUB(NOW(), INTERVAL 10 DAY), 'annulé',   'Annulé par le médecin',          NULL),
(9,  1, DATE_ADD(NOW(), INTERVAL 2  DAY), 'planifié', 'Premier contact',                NULL),
(9,  5, DATE_SUB(NOW(), INTERVAL 3  DAY), 'effectué', 'Présentation Oméprazole',        'Très bon accueil'),
(10, 3, DATE_ADD(NOW(), INTERVAL 4  DAY), 'planifié', 'Suivi trimestriel',              NULL),
(10, 6, DATE_SUB(NOW(), INTERVAL 1  DAY), 'effectué', 'Présentation Sertraline',        'Médecin prescripteur habituel'),
(11, 7, DATE_ADD(NOW(), INTERVAL 6  DAY), 'planifié', 'Nouvelle visite',                NULL);

-- Médicaments présentés
INSERT INTO rdv_medicaments (rendez_vous_id, medicament_id, quantite) VALUES
(1, 2, 3), (1, 1, 5),
(2, 8, 2), (2, 9, 2),
(3, 10, 4),
(4, 12, 3),
(6, 1, 2),
(7, 11, 5),
(8, 10, 3),
(9, 13, 2);
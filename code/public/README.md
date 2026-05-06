# 🏥 GSB Visite — Application de gestion des visites médicales

Application web PHP permettant la gestion des rendez-vous médicaux, des médicaments et des professionnels de santé pour le laboratoire **Galaxy Swiss Bourdin (GSB)**.

---

## 📋 Présentation

Dans le cadre de la modernisation de l'activité de visite médicale de GSB, cette application permet de gérer et suivre les visites des collaborateurs terrain auprès des professionnels de santé.

### Rôles disponibles

| Rôle | Accès |
|------|-------|
| **Chef régional** | Accès complet : gestion des délégués, visiteurs, médicaments, professionnels et tous les rendez-vous |
| **Délégué régional** | Gestion de son équipe de visiteurs, des rendez-vous et des professionnels de santé |
| **Visiteur médical** | Consultation de ses propres rendez-vous, accès au catalogue médicaments en lecture seule |

---

## ⚙️ Technologies utilisées

- **PHP 8.x** — Langage de développement backend
- **MySQL 8.0** — Base de données
- **Apache 2.4** — Serveur web
- **Docker / Docker Compose** — Conteneurisation de l'environnement
- **phpMyAdmin** — Interface d'administration de la base de données
- **HTML / CSS / JavaScript** — Frontend

---

## 🚀 Installation et lancement

### Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installé et démarré

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/saxhou9z/projet-perso.git
cd projet-perso

# 2. Lancer l'application
docker compose up -d

# 3. Ouvrir dans le navigateur
# Application : http://localhost
# phpMyAdmin  : http://localhost:8081
```

> ⚠️ Au premier lancement, attendre ~15 secondes que MySQL soit prêt avant d'accéder à l'application.

---

## 🔐 Comptes de test

| Identifiant | Mot de passe | Rôle |
|-------------|--------------|------|
| `marc.rousseau@gsb.fr` | `chef123` | Chef régional |
| `p.laurent@gsb.fr` | `delegue123` | Délégué régional |
| `jean.dupont@gsb.fr` | `visiteur123` | Visiteur médical |

---

## 📁 Structure du projet

```
projet-perso/
├── docker-compose.yml      # Configuration Docker
├── Dockerfile              # Image PHP/Apache
├── database.sql            # Structure et données de la BDD
├── config.php              # Configuration base de données
├── index.php               # Router principal + Dashboard
├── login.php               # Authentification
├── logout.php              # Déconnexion
├── header.php              # Barre de navigation
├── medicaments.php         # Gestion des médicaments
├── professionnels.php      # Gestion des professionnels de santé
├── rendez_vous.php         # Gestion des rendez-vous
├── gestion_visiteurs.php   # Gestion des visiteurs (délégué)
├── gestion_equipe.php      # Gestion de l'équipe (chef)
└── style.css               # Feuille de styles
```

---

## 🗄️ Base de données

La base est automatiquement initialisée au démarrage de Docker via `database.sql`.

Elle contient les tables suivantes : `utilisateurs`, `regions`, `medicaments`, `familles`, `professionnels`, `rendez_vous`, `rdv_medicaments`.

---

## 👨‍💻 Auteur

**Poussy Sacha** — BTS SIO SLAM — Lycée Condorcet, Belfort — Session 2026

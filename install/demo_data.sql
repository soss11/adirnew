-- Mini CRM - Données de démonstration

-- Types de cotisation
INSERT INTO cotisation_types (nom, description, montant, duree_mois, actif) VALUES
('Annuelle Individuelle', 'Cotisation annuelle pour une personne', 30.00, 12, 1),
('Annuelle Famille', 'Cotisation annuelle pour toute la famille', 50.00, 12, 1),
('Étudiant', 'Tarif réduit pour les étudiants (sur justificatif)', 15.00, 12, 1),
('Soutien', 'Cotisation de soutien pour les généreux donateurs', 100.00, 12, 1),
('Mensuelle', 'Cotisation au mois', 5.00, 1, 1);

-- Membres de démonstration (mot de passe: demo123)
INSERT INTO users (email, password, nom, prenom, role, telephone, adresse, ville, code_postal, pays_origine, nationalite, langue_parlee, date_arrivee, date_naissance, profession, notes, actif, created_at) VALUES
('marie.dupont@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dupont', 'Marie', 'membre', '06 12 34 56 78', '15 rue de la Paix', 'Paris', '75001', 'France', 'Française', 'Français, Anglais', '2020-03-15', '1985-06-20', 'Enseignante', 'Membre très active, participe à tous les événements', 1, '2023-01-15 10:00:00'),
('jean.martin@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Martin', 'Jean', 'membre', '06 23 45 67 89', '8 avenue des Champs', 'Lyon', '69001', 'Belgique', 'Belge', 'Français, Néerlandais', '2019-09-01', '1978-11-03', 'Ingénieur', 'A proposé d''organiser un atelier informatique', 1, '2023-02-20 14:30:00'),
('sophie.bernard@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bernard', 'Sophie', 'gestionnaire', '06 34 56 78 90', '22 boulevard Victor Hugo', 'Marseille', '13001', 'Suisse', 'Suisse', 'Français, Allemand', '2018-05-10', '1990-02-14', 'Comptable', 'Gestionnaire bénévole depuis 2023', 1, '2023-03-10 09:15:00'),
('pierre.leroy@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Leroy', 'Pierre', 'membre', '06 45 67 89 01', '5 place de la République', 'Toulouse', '31000', 'Canada', 'Canadien', 'Français, Anglais', '2021-01-20', '1995-08-25', 'Étudiant', 'Étudiant en échange universitaire', 1, '2023-04-05 11:45:00'),
('isabelle.moreau@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Moreau', 'Isabelle', 'membre', '06 56 78 90 12', '18 rue du Commerce', 'Bordeaux', '33000', 'France', 'Française', 'Français', '2022-06-30', '1970-04-18', 'Médecin', 'Peut aider pour les questions de santé', 1, '2023-05-12 16:20:00'),
('lucas.petit@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Petit', 'Lucas', 'membre', '06 67 89 01 23', '30 rue de Rivoli', 'Nice', '06000', 'Luxembourg', 'Luxembourgeois', 'Français, Allemand, Luxembourgeois', '2020-11-05', '1988-12-30', 'Avocat', '', 1, '2023-06-18 13:00:00'),
('emma.roux@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Roux', 'Emma', 'membre', '06 78 90 12 34', '12 avenue Foch', 'Strasbourg', '67000', 'Sénégal', 'Sénégalaise', 'Français, Wolof', '2019-03-22', '1992-07-08', 'Infirmière', 'Très impliquée dans les actions sociales', 1, '2023-07-22 10:30:00'),
('antoine.girard@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Girard', 'Antoine', 'membre', '06 89 01 23 45', '7 rue Pasteur', 'Nantes', '44000', 'Maroc', 'Marocain', 'Français, Arabe', '2021-08-14', '1983-01-12', 'Chef cuisinier', 'A proposé de faire la cuisine pour les événements', 1, '2023-08-30 15:45:00'),
('claire.lambert@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lambert', 'Claire', 'membre', '06 90 12 34 56', '25 rue de la Liberté', 'Lille', '59000', 'Côte d''Ivoire', 'Ivoirienne', 'Français', '2022-02-28', '1998-09-05', 'Étudiante', 'Nouvelle membre, cherche à s''intégrer', 1, '2023-09-14 09:00:00'),
('thomas.dubois@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dubois', 'Thomas', 'membre', '06 01 23 45 67', '40 boulevard Haussmann', 'Paris', '75009', 'Haïti', 'Haïtien', 'Français, Créole', '2020-07-19', '1975-05-22', 'Musicien', 'Peut animer les soirées musicales', 1, '2023-10-20 14:15:00');

-- Cotisations de démonstration
INSERT INTO cotisations (user_id, cotisation_type_id, montant, date_debut, date_fin, date_paiement, mode_paiement, statut, notes) VALUES
(2, 1, 30.00, '2024-01-15', '2025-01-14', '2024-01-15', 'cheque', 'paye', 'Chèque n°123456'),
(3, 1, 30.00, '2024-02-20', '2025-02-19', '2024-02-20', 'carte', 'paye', ''),
(4, 2, 50.00, '2024-03-10', '2025-03-09', '2024-03-10', 'especes', 'paye', 'Cotisation famille'),
(5, 3, 15.00, '2024-04-05', '2025-04-04', '2024-04-05', 'virement', 'paye', 'Carte étudiant vérifiée'),
(6, 1, 30.00, '2024-05-12', '2025-05-11', '2024-05-12', 'especes', 'paye', ''),
(7, 4, 100.00, '2024-06-18', '2025-06-17', '2024-06-18', 'virement', 'paye', 'Membre soutien'),
(8, 1, 30.00, '2024-07-22', '2025-07-21', '2024-07-22', 'cheque', 'paye', ''),
(9, 1, 30.00, '2024-01-30', '2025-01-29', NULL, 'especes', 'en_attente', 'Relance effectuée'),
(10, 3, 15.00, '2024-09-14', '2025-09-13', '2024-09-14', 'carte', 'paye', ''),
(11, 1, 30.00, '2023-10-20', '2024-10-19', '2023-10-20', 'especes', 'paye', 'À renouveler'),
(2, 1, 30.00, '2023-01-15', '2024-01-14', '2023-01-15', 'cheque', 'paye', 'Année précédente');

-- Événements de démonstration
INSERT INTO evenements (titre, description, type, date_debut, date_fin, lieu, adresse, places_max, prix_membre, prix_non_membre, statut, organisateur_id, created_at) VALUES
('Soirée Francophone de Noël', 'Grande soirée de Noël avec repas traditionnel, musique et tombola. Venez nombreux célébrer les fêtes en famille !', 'soiree', '2024-12-20 19:00:00', '2024-12-21 01:00:00', 'Salle des Fêtes', '10 rue de la Mairie, 75001 Paris', 100, 15.00, 25.00, 'publie', 4, '2024-10-01 10:00:00'),
('Atelier Cuisine Québécoise', 'Découvrez les saveurs du Québec avec notre chef Antoine. Au menu : poutine, tourtière et tarte au sucre !', 'atelier', '2024-12-15 14:00:00', '2024-12-15 17:00:00', 'Centre Culturel', '5 place du Marché, 75002 Paris', 20, 10.00, 15.00, 'publie', 9, '2024-10-15 14:30:00'),
('Spectacle de Chanson Française', 'Concert de Thomas Dubois et ses musiciens. Répertoire de Brel, Piaf, Aznavour et créations originales.', 'spectacle', '2025-01-25 20:00:00', '2025-01-25 22:30:00', 'Théâtre Municipal', '15 avenue des Arts, 75003 Paris', 150, 12.00, 18.00, 'publie', 11, '2024-11-01 09:00:00'),
('Réunion Mensuelle des Membres', 'Réunion ouverte à tous les membres pour discuter des projets à venir et des besoins de la communauté.', 'reunion', '2024-12-10 18:30:00', '2024-12-10 20:00:00', 'Local de l''Association', '8 rue des Associations, 75004 Paris', 50, 0.00, 0.00, 'publie', 4, '2024-11-20 11:00:00'),
('Tombola de la Saint-Valentin', 'Grande tombola avec de nombreux lots à gagner : week-end pour deux, dîner gastronomique, paniers gourmands...', 'tombola', '2025-02-14 19:00:00', '2025-02-14 23:00:00', 'Restaurant Le Francophone', '20 rue Montmartre, 75005 Paris', 80, 5.00, 8.00, 'brouillon', 4, '2024-11-25 16:00:00'),
('Soirée Jeux de Société', 'Venez découvrir ou redécouvrir les jeux de société francophones dans une ambiance conviviale.', 'soiree', '2025-01-10 18:00:00', '2025-01-10 22:00:00', 'Café des Amis', '12 rue de la Paix, 75006 Paris', 30, 5.00, 8.00, 'publie', 4, '2024-11-28 10:00:00'),
('Atelier Conversation Français', 'Atelier pour pratiquer le français dans un cadre décontracté. Tous niveaux bienvenus !', 'atelier', '2024-12-08 10:00:00', '2024-12-08 12:00:00', 'Bibliothèque Municipale', '3 square du Livre, 75007 Paris', 15, 0.00, 5.00, 'publie', 8, '2024-11-10 08:30:00'),
('Fête de la Francophonie', 'Célébration de la journée internationale de la Francophonie avec spectacles, stands et gastronomie.', 'soiree', '2025-03-20 14:00:00', '2025-03-20 22:00:00', 'Parc des Expositions', '1 boulevard de l''International, 75008 Paris', 500, 8.00, 12.00, 'brouillon', 4, '2024-11-30 14:00:00');

-- Inscriptions aux événements
INSERT INTO inscriptions (evenement_id, user_id, nom_participant, prenom_participant, email_participant, nombre_places, montant, statut, mode_paiement, date_paiement, notes) VALUES
(1, 2, 'Dupont', 'Marie', 'marie.dupont@email.com', 2, 30.00, 'confirme', 'carte', '2024-11-15', 'Vient avec son mari'),
(1, 3, 'Martin', 'Jean', 'jean.martin@email.com', 1, 15.00, 'confirme', 'especes', '2024-11-16', ''),
(1, 6, 'Moreau', 'Isabelle', 'isabelle.moreau@email.com', 3, 45.00, 'confirme', 'cheque', '2024-11-18', 'Avec ses 2 enfants'),
(1, 8, 'Roux', 'Emma', 'emma.roux@email.com', 1, 15.00, 'en_attente', 'especes', NULL, 'Paiement en attente'),
(1, NULL, 'Durand', 'Philippe', 'philippe.durand@email.com', 2, 50.00, 'confirme', 'carte', '2024-11-20', 'Non membre'),
(2, 2, 'Dupont', 'Marie', 'marie.dupont@email.com', 1, 10.00, 'confirme', 'especes', '2024-11-25', ''),
(2, 5, 'Leroy', 'Pierre', 'pierre.leroy@email.com', 1, 10.00, 'confirme', 'carte', '2024-11-26', ''),
(2, 10, 'Lambert', 'Claire', 'claire.lambert@email.com', 1, 10.00, 'confirme', 'especes', '2024-11-27', ''),
(3, 3, 'Martin', 'Jean', 'jean.martin@email.com', 2, 24.00, 'confirme', 'virement', '2024-12-01', ''),
(3, 7, 'Petit', 'Lucas', 'lucas.petit@email.com', 4, 48.00, 'confirme', 'carte', '2024-12-02', 'Réservation pour amis'),
(4, 4, 'Bernard', 'Sophie', 'sophie.bernard@email.com', 1, 0.00, 'confirme', 'gratuit', NULL, ''),
(4, 6, 'Moreau', 'Isabelle', 'isabelle.moreau@email.com', 1, 0.00, 'confirme', 'gratuit', NULL, ''),
(4, 9, 'Girard', 'Antoine', 'antoine.girard@email.com', 1, 0.00, 'confirme', 'gratuit', NULL, ''),
(6, 2, 'Dupont', 'Marie', 'marie.dupont@email.com', 1, 5.00, 'en_attente', 'especes', NULL, ''),
(7, 10, 'Lambert', 'Claire', 'claire.lambert@email.com', 1, 0.00, 'confirme', 'gratuit', NULL, 'Membre'),
(7, NULL, 'Petit', 'Jean', 'jean.petit@email.com', 1, 5.00, 'confirme', 'especes', '2024-12-01', 'Non membre');

-- Notes sur les membres
INSERT INTO membre_notes (user_id, auteur_id, contenu, created_at) VALUES
(2, 4, 'Marie est très impliquée dans l''association. Elle aide souvent à l''organisation des événements.', '2024-06-15 10:30:00'),
(2, 4, 'A suggéré de créer un groupe de conversation en anglais pour les membres.', '2024-09-20 14:00:00'),
(9, 4, 'Antoine a proposé ses services de chef pour le repas de Noël. À recontacter en novembre.', '2024-08-10 11:00:00'),
(11, 4, 'Thomas peut fournir la sono pour les événements. Contact à garder précieusement !', '2024-10-25 16:30:00'),
(5, 4, 'Pierre termine ses études en juin. Voir s''il souhaite continuer son adhésion après.', '2024-11-01 09:00:00'),
(10, 4, 'Claire cherche un logement. Si quelqu''un a des contacts, la mettre en relation.', '2024-09-18 15:00:00');

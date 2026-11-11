<?php
require_once __DIR__ . '/db.php';
require_once 'auth.php';
requireRole('Professeur');
session_start();

try {
    $bdd = getDBConnection();
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// ============================================================
// 1. RÉCUPÉRATION DU PROFESSEUR CONNECTÉ
// ============================================================
$id_user = $_SESSION['id_user'] ?? 0;

$reqProf = $bdd->prepare("
    SELECT p.id_prof, p.specialite, p.diplome, u.nom, u.prenom, u.photo
    FROM professeurs p 
    INNER JOIN users u ON p.id_user = u.id_user 
    WHERE p.id_user = ?
");
$reqProf->execute([$id_user]);
$prof = $reqProf->fetch(PDO::FETCH_ASSOC);

if ($prof === false) {
    $id_prof = 0;
    $prof = [
        'nom' => 'Inconnu',
        'prenom' => '',
        'diplome' => 'Non renseigné',
        'specialite' => 'Non renseignée',
        'photo' => 'default.png'
    ];
} else {
    $id_prof = $prof['id_prof'];
}

// ============================================================
// 2. RÉCUPÉRATION DES HORAIRES DU PROFESSEUR AVEC FACULTÉ
// ============================================================
$reqPlanning = $bdd->prepare("
    SELECT 
        h.id_horaire,
        h.jour, 
        h.heure_debut, 
        h.heure_fin, 
        h.salle,
        h.type_cours,
        h.semaine,
        c.nom_cours,
        cl.niveau,
        d.nom_dept,
        d.faculte,
        CONCAT(u.nom, ' ', u.prenom) AS enseignant
    FROM horaires h
    INNER JOIN cours c ON h.id_cours = c.id_cours
    INNER JOIN classes cl ON h.id_classe = cl.id_classe
    INNER JOIN departements d ON cl.id_dept = d.id_dept
    LEFT JOIN professeurs p ON h.id_prof = p.id_prof
    LEFT JOIN users u ON p.id_user = u.id_user
    WHERE h.id_prof = ? OR c.id_prof = ?
    ORDER BY FIELD(h.jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'), h.heure_debut ASC
");
$reqPlanning->execute([$id_prof, $id_prof]);
$planning = $reqPlanning->fetchAll(PDO::FETCH_ASSOC);

// Organiser le planning par jour
$emplois_du_temps = [
    'Lundi' => [],
    'Mardi' => [],
    'Mercredi' => [],
    'Jeudi' => [],
    'Vendredi' => [],
    'Samedi' => []
];

foreach ($planning as $creneau) {
    if (isset($emplois_du_temps[$creneau['jour']])) {
        $emplois_du_temps[$creneau['jour']][] = $creneau;
    }
}

// ============================================================
// 3. STATISTIQUES
// ============================================================
$totalCours = count($planning);
$totalSalles = count(array_unique(array_filter(array_column($planning, 'salle'))));
$totalMatieres = count(array_unique(array_column($planning, 'nom_cours')));
$semaineActuelle = date('W');

// Photo de profil
$photo_profil = !empty($prof['photo']) && file_exists("uploads/" . $prof['photo']) ? $prof['photo'] : 'default.png';

// Récupérer les semaines disponibles pour le filtre
$semainesDisponibles = array_unique(array_column($planning, 'semaine'));
sort($semainesDisponibles);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Emploi du Temps — UniVerse</title>
    <style>
        :root {
            --bg-light: #f8fafc;
            --sidebar-color: #0f172a;
            --text-main: #334155;
            --accent-blue: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --border-color: #e2e8f0;
            --time-color: #0284c7;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-main); display: flex; min-height: 100vh; }

        /* SIDEBAR */
        .sidebar { 
            width: 260px; 
            background-color: var(--sidebar-color); 
            color: white; 
            padding: 30px 20px; 
            position: fixed; 
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .sidebar .logo { font-size: 24px; font-weight: 800; margin-bottom: 25px; }
        
        .user-profile {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
        }
        .profile-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent-blue);
            margin-bottom: 8px;
        }
        .user-profile .name { font-weight: 600; font-size: 14px; }
        .user-profile .title { font-size: 11px; color: #94a3b8; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 700; border-radius: 6px; margin-top: 6px; }
        .badge.professeur { background: #8b5cf6; }

        .nav-links { list-style: none; display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .nav-links a { color: #94a3b8; text-decoration: none; padding: 12px; border-radius: 8px; display: block; font-weight: 600; transition: all 0.2s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-links a.logout { color: var(--error); margin-top: auto; }

        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 40px; }
        header { 
            margin-bottom: 30px; 
            border-bottom: 1px solid var(--border-color); 
            padding-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        header h1 { font-size: 26px; color: #1e293b; }
        .info-badge { 
            background: white; 
            border: 1px solid var(--border-color); 
            padding: 8px 16px; 
            border-radius: 8px; 
            font-weight: 600;
            font-size: 14px;
        }

        .faculte-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
            font-size: 11px;
        }

        /* STATS CARTES */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .stat-card .number {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent-blue);
        }
        .stat-card .label {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 4px;
        }

        /* FILTRES */
        .filter-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-bar label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }
        .filter-bar select, .filter-bar input {
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-light);
            outline: none;
        }
        .filter-bar select:focus, .filter-bar input:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .btn-filter {
            background: var(--accent-blue);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-filter:hover { background: #1d4ed8; }
        .btn-reset {
            background: #64748b;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-reset:hover { background: #475569; }

        /* VUE LISTE */
        .schedule-grid { display: flex; flex-direction: column; gap: 20px; }
        
        .day-panel { 
            background: white; 
            border-radius: 14px; 
            border: 1px solid var(--border-color); 
            padding: 25px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .day-title { 
            font-size: 18px; 
            font-weight: 700; 
            color: #1e293b; 
            margin-bottom: 15px; 
            border-left: 4px solid var(--accent-blue); 
            padding-left: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .day-title .badge-count {
            background: #f1f5f9;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }

        .course-list { display: flex; flex-direction: column; gap: 12px; }
        .course-card { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 15px 20px; 
            background: #f8fafc; 
            border-radius: 10px; 
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--time-color);
            transition: all 0.2s;
        }
        .course-card:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }
        
        .course-time { font-size: 14px; font-weight: 700; color: var(--time-color); min-width: 130px; }
        .course-info { flex-grow: 1; margin: 0 15px; }
        .course-name { font-size: 15px; font-weight: 600; color: #0f172a; }
        .course-class { font-size: 13px; color: #64748b; margin-top: 2px; }
        
        .course-room { 
            background: #e0f2fe; 
            color: #0369a1; 
            padding: 4px 12px; 
            border-radius: 6px; 
            font-size: 12px; 
            font-weight: 700; 
            text-transform: uppercase;
            margin-right: 10px;
        }

        .course-type {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }
        .course-type.CM { background: #dbeafe; color: #1e40af; }
        .course-type.TD { background: #d1fae5; color: #065f46; }
        .course-type.TP { background: #fef3c7; color: #92400e; }

        .semaine-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .no-course { color: #94a3b8; font-style: italic; font-size: 14px; padding: 5px 0; }

        /* VUE TABLEAU */
        .table-view {
            display: none;
        }
        .table-view.active {
            display: block;
        }
        .list-view.active {
            display: flex;
        }

        .view-toggle {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .view-toggle button {
            padding: 6px 16px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: white;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
        }
        .view-toggle button.active {
            background: var(--accent-blue);
            color: white;
            border-color: var(--accent-blue);
        }
        .view-toggle button:hover:not(.active) {
            background: #f1f5f9;
        }

        .table-view table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .table-view th {
            background: #f8fafc;
            padding: 12px 14px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
        }
        .table-view td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
        }
        .table-view tr:hover {
            background: #f8fafc;
        }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; padding: 20px; }
            .main-content { margin-left: 0; width: 100%; padding: 20px; }
            header { flex-direction: column; align-items: flex-start; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .course-card { flex-direction: column; align-items: flex-start; gap: 8px; }
            .course-time { min-width: unset; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">🎓 UniVerse</div>
        <div class="user-profile">
            <img src="uploads/<?= htmlspecialchars($photo_profil); ?>" alt="Photo" class="profile-img">
            <p class="name">Prof. <?= htmlspecialchars($prof['nom'] . ' ' . $prof['prenom']); ?></p>
            <p class="title"><?= htmlspecialchars($prof['specialite'] ?? 'Enseignant'); ?></p>
            <span class="badge professeur">Professeur</span>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard_professeur.php">📊 Vue d'ensemble</a></li>
            <li><a href="saisie_notes.php">✍️ Saisie des Notes</a></li>
            <li><a href="emploi_temps_prof.php" class="active">📚 Mes Emplois du Temps</a></li>
            <li><a href="mon_profil.php">👤 Mon Profil</a></li>
            <li><a href="logout.php" class="logout">🚪 Déconnexion</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1>📚 Mon Emploi du Temps</h1>
            <div class="info-badge">📅 Semaine <?= $semaineActuelle ?> - Année <?= date('Y') ?></div>
        </header>

        <!-- STATISTIQUES -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?= $totalCours; ?></div>
                <div class="label">📋 Total Cours</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $totalSalles; ?></div>
                <div class="label">🏫 Salles</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= $totalMatieres; ?></div>
                <div class="label">📖 Matières</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count(array_filter($emplois_du_temps, function($j) { return count($j) > 0; })); ?></div>
                <div class="label">📆 Jours occupés</div>
            </div>
        </div>

        <!-- FILTRES -->
        <div class="filter-bar">
            <label for="filterSemaine">Semaine</label>
            <select id="filterSemaine">
                <option value="">Toutes</option>
                <?php foreach($semainesDisponibles as $s): ?>
                    <option value="<?= $s; ?>">Semaine <?= $s; ?></option>
                <?php endforeach; ?>
            </select>

            <label for="filterType">Type</label>
            <select id="filterType">
                <option value="">Tous</option>
                <option value="CM">CM</option>
                <option value="TD">TD</option>
                <option value="TP">TP</option>
            </select>

            <label for="filterMatiere">Matière</label>
            <input type="text" id="filterMatiere" placeholder="Rechercher..." style="min-width:150px;">

            <button class="btn-filter" onclick="appliquerFiltres()">🔍 Filtrer</button>
            <button class="btn-reset" onclick="reinitialiserFiltres()">✖️ Réinitialiser</button>

            <div class="view-toggle">
                <button class="active" onclick="changerVue('list')">📋 Liste</button>
                <button onclick="changerVue('table')">📊 Tableau</button>
            </div>
        </div>

        <!-- VUE LISTE -->
        <div class="schedule-grid list-view active" id="listView">
            <?php foreach ($emplois_du_temps as $jour => $cours_du_jour): 
                $nb_cours = count($cours_du_jour);
            ?>
                <div class="day-panel" data-jour="<?= $jour; ?>">
                    <div class="day-title">
                        <?= $jour; ?>
                        <span class="badge-count"><?= $nb_cours; ?> cours</span>
                    </div>
                    <div class="course-list">
                        <?php if ($nb_cours > 0): ?>
                            <?php foreach ($cours_du_jour as $c): ?>
                                <div class="course-card" 
                                     data-semaine="<?= $c['semaine'] ?? ''; ?>"
                                     data-type="<?= $c['type_cours'] ?? ''; ?>"
                                     data-matiere="<?= strtolower($c['nom_cours']); ?>">
                                    <div class="course-time">
                                        🕒 <?= date('H:i', strtotime($c['heure_debut'])); ?> - <?= date('H:i', strtotime($c['heure_fin'])); ?>
                                    </div>
                                    <div class="course-info">
                                        <div class="course-name">
                                            <?= htmlspecialchars($c['nom_cours']); ?>
                                            <span class="course-type <?= htmlspecialchars($c['type_cours'] ?? 'CM'); ?>">
                                                <?= htmlspecialchars($c['type_cours'] ?? 'CM'); ?>
                                            </span>
                                            <?php if (!empty($c['semaine'])): ?>
                                                <span class="semaine-badge">Sem. <?= $c['semaine']; ?></span>
                                            <?php endif; ?>
                                            <span class="faculte-badge" style="margin-left:5px;"><?= htmlspecialchars($c['faculte'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="course-class">
                                            Classe : <strong><?= htmlspecialchars($c['niveau']); ?></strong> 
                                            | Dép. : <?= htmlspecialchars($c['nom_dept']); ?>
                                            <?php if (!empty($c['enseignant'])): ?>
                                                | Enseignant : <?= htmlspecialchars($c['enseignant']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div class="course-room">
                                            🚪 <?= htmlspecialchars($c['salle'] ?: 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-course">📭 Aucun cours prévu ce jour.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- VUE TABLEAU -->
        <div class="table-view" id="tableView">
            <div class="panel" style="background:white; padding:20px; border-radius:12px; border:1px solid var(--border-color);">
                <table>
                    <thead>
                        <tr>
                            <th>📆 Jour</th>
                            <th>⏱️ Horaire</th>
                            <th>📚 Cours</th>
                            <th>📋 Type</th>
                            <th>🏛️ Classe</th>
                            <th>🏫 Faculté</th>
                            <th>🏫 Salle</th>
                            <th>📅 Semaine</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php 
                        $joursOrdre = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                        foreach ($joursOrdre as $jour):
                            if (isset($emplois_du_temps[$jour]) && count($emplois_du_temps[$jour]) > 0):
                                foreach ($emplois_du_temps[$jour] as $c):
                        ?>
                            <tr data-semaine="<?= $c['semaine'] ?? ''; ?>" data-type="<?= $c['type_cours'] ?? ''; ?>" data-matiere="<?= strtolower($c['nom_cours']); ?>">
                                <td><strong><?= $jour; ?></strong></td>
                                <td><?= date('H:i', strtotime($c['heure_debut'])); ?> - <?= date('H:i', strtotime($c['heure_fin'])); ?></td>
                                <td><?= htmlspecialchars($c['nom_cours']); ?></td>
                                <td><span class="course-type <?= htmlspecialchars($c['type_cours'] ?? 'CM'); ?>"><?= htmlspecialchars($c['type_cours'] ?? 'CM'); ?></span></td>
                                <td><?= htmlspecialchars($c['niveau']); ?></td>
                                <td><span class="faculte-badge"><?= htmlspecialchars($c['faculte'] ?? 'N/A'); ?></span></td>
                                <td><?= htmlspecialchars($c['salle'] ?: '-'); ?></td>
                                <td><?= $c['semaine'] ?? '-'; ?></td>
                            </tr>
                        <?php 
                                endforeach;
                            endif;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
    function appliquerFiltres() {
        const semaine = document.getElementById('filterSemaine').value;
        const type = document.getElementById('filterType').value;
        const matiere = document.getElementById('filterMatiere').value.toLowerCase().trim();

        const jours = document.querySelectorAll('.day-panel');
        jours.forEach(jour => {
            const cards = jour.querySelectorAll('.course-card');
            let jourVisible = false;

            cards.forEach(card => {
                const cardSemaine = card.getAttribute('data-semaine');
                const cardType = card.getAttribute('data-type');
                const cardMatiere = card.getAttribute('data-matiere');

                let visible = true;
                if (semaine !== '' && cardSemaine !== semaine) visible = false;
                if (type !== '' && cardType !== type) visible = false;
                if (matiere !== '' && !cardMatiere.includes(matiere)) visible = false;

                card.style.display = visible ? 'flex' : 'none';
                if (visible) jourVisible = true;
            });

            const noCourse = jour.querySelector('.no-course');
            if (noCourse) {
                noCourse.style.display = jourVisible ? 'none' : 'block';
            }

            const badge = jour.querySelector('.badge-count');
            if (badge) {
                const visibleCards = jour.querySelectorAll('.course-card[style*="display: flex"]');
                badge.textContent = visibleCards.length + ' cours';
            }
        });

        const rows = document.querySelectorAll('#tableBody tr');
        rows.forEach(row => {
            const rowSemaine = row.getAttribute('data-semaine');
            const rowType = row.getAttribute('data-type');
            const rowMatiere = row.getAttribute('data-matiere');

            let visible = true;
            if (semaine !== '' && rowSemaine !== semaine) visible = false;
            if (type !== '' && rowType !== type) visible = false;
            if (matiere !== '' && !rowMatiere.includes(matiere)) visible = false;

            row.style.display = visible ? '' : 'none';
        });
    }

    function reinitialiserFiltres() {
        document.getElementById('filterSemaine').value = '';
        document.getElementById('filterType').value = '';
        document.getElementById('filterMatiere').value = '';
        appliquerFiltres();
    }

    function changerVue(vue) {
        const listView = document.getElementById('listView');
        const tableView = document.getElementById('tableView');
        const btns = document.querySelectorAll('.view-toggle button');

        btns.forEach(btn => btn.classList.remove('active'));

        if (vue === 'list') {
            listView.classList.add('active');
            listView.style.display = 'flex';
            tableView.classList.remove('active');
            tableView.style.display = 'none';
            btns[0].classList.add('active');
        } else {
            tableView.classList.add('active');
            tableView.style.display = 'block';
            listView.classList.remove('active');
            listView.style.display = 'none';
            btns[1].classList.add('active');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('filterSemaine').addEventListener('change', appliquerFiltres);
        document.getElementById('filterType').addEventListener('change', appliquerFiltres);
        document.getElementById('filterMatiere').addEventListener('input', appliquerFiltres);
    });
    </script>

</body>
</html>
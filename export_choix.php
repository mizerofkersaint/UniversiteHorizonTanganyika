<?php
// ============================================================
// export_choix.php — Choix de la table et du format d'export
// ============================================================
require_once 'auth.php';
requireRole(['Administrateur', 'Doyen', 'Comptable', 'Recteur', 'Agent']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$table    = isset($_GET['table']) ? trim($_GET['table']) : '';
$filename = isset($_GET['filename']) ? trim($_GET['filename']) : 'Export';

$tables_disponibles = [
    'etudiants'    => '👨‍🎓 Étudiants',
    'professeurs'  => '👨‍🏫 Professeurs',
    'cours'        => '📚 Cours',
    'paiements'    => '💰 Paiements',
    'notes'        => '📝 Notes',
    'inscriptions' => '📋 Inscriptions',
    'users'        => '👥 Utilisateurs',
    'departements' => '🏛️ Départements',
    'classes'      => '📖 Classes'
];

$formats = [
    'xls' => ['label' => '📊 Excel (.xls)',   'desc' => 'Compatible Microsoft Excel & LibreOffice', 'color' => '#10b981'],
    'csv' => ['label' => '📄 CSV (.csv)',     'desc' => 'Universel — séparateur ";" pour Excel FR', 'color' => '#2563eb'],
    'pdf' => ['label' => '📕 PDF (.pdf)',     'desc' => 'Document prêt à imprimer ou archiver',      'color' => '#dc2626']
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export de Données — UniVerse</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #eef3f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,.08); max-width: 640px; width: 100%; }
        h1 { color: #003366; text-align: center; margin-bottom: 8px; font-size: 24px; }
        .subtitle { text-align: center; color: #64748b; margin-bottom: 28px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; color: #334155; margin-bottom: 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.3px; }
        select, input[type="text"] { width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 15px; outline: none; background: #f8fafc; transition: all 0.2s; }
        select:focus, input[type="text"]:focus { border-color: #0055aa; box-shadow: 0 0 0 3px rgba(0,85,170,.10); background: white; }

        /* Formats en cards */
        .format-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .format-card { position: relative; cursor: pointer; }
        .format-card input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
        .format-card .format-content {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            padding: 16px 12px; border: 2px solid #e2e8f0; border-radius: 12px;
            text-align: center; transition: all 0.2s; background: white;
        }
        .format-card .format-icon { font-size: 28px; }
        .format-card .format-label { font-weight: 700; font-size: 13px; color: #1e293b; }
        .format-card .format-desc { font-size: 11px; color: #64748b; line-height: 1.3; }
        .format-card input[type="radio"]:checked + .format-content {
            border-color: #003366; background: #eff6ff; box-shadow: 0 4px 12px rgba(0,51,102,0.15);
        }
        .format-card:hover .format-content { border-color: #94a3b8; }

        .btn-container { display: flex; gap: 12px; margin-top: 26px; }
        .btn-export { flex: 1; background: linear-gradient(135deg, #003366, #0055aa); color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.3s; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-export:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,51,102,0.3); }
        .btn-cancel { flex: 1; background: #e5e7eb; color: #111827; padding: 14px; border-radius: 10px; font-weight: 700; text-decoration: none; text-align: center; font-size: 15px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .btn-cancel:hover { background: #d1d5db; }

        .info-box { background: #eff6ff; padding: 14px 16px; border-radius: 10px; margin-bottom: 22px; border-left: 4px solid #2563eb; font-size: 13px; color: #1e40af; line-height: 1.5; }
        .info-box strong { color: #003366; }

        @media (max-width: 600px) {
            .container { padding: 25px; }
            .format-grid { grid-template-columns: 1fr; }
            .btn-container { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📊 Export de Données</h1>
    <p class="subtitle">Choisissez la table et le format d'export</p>

    <form action="export_universel.php" method="GET">
        <div class="info-box">
            💡 <strong>Note :</strong> Les filtres de recherche actifs (nom, département, etc.) seront automatiquement appliqués à l'export.
        </div>

        <div class="form-group">
            <label>📋 Table à exporter</label>
            <select name="table" required>
                <option value="">— Sélectionner une table —</option>
                <?php foreach ($tables_disponibles as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= ($table === $key) ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>📁 Format d'export</label>
            <div class="format-grid">
                <?php foreach ($formats as $key => $f): ?>
                    <label class="format-card">
                        <input type="radio" name="format" value="<?= $key ?>" <?= ($key === 'xls') ? 'checked' : '' ?>>
                        <div class="format-content">
                            <span class="format-icon"><?= explode(' ', $f['label'])[0] ?></span>
                            <span class="format-label"><?= htmlspecialchars(str_replace(explode(' ', $f['label'])[0] . ' ', '', $f['label'])) ?></span>
                            <span class="format-desc"><?= htmlspecialchars($f['desc']) ?></span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label>📄 Nom du fichier</label>
            <input type="text" name="filename" value="<?= htmlspecialchars($filename) ?>" placeholder="Ex: Liste_Etudiants">
        </div>

        <div class="btn-container">
            <button type="submit" class="btn-export">
                <i>🚀</i> Lancer l'export
            </button>
            <a href="javascript:history.back()" class="btn-cancel">
                <i>❌</i> Annuler
            </a>
        </div>
    </form>
</div>
</body>
</html>
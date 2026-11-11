// ============================================================
// UNIVERSE — Gestion du thème (clair/sombre)
// ============================================================

(function() {
    // Applique le thème IMMÉDIATEMENT au chargement (évite le flash)
    const theme = localStorage.getItem('universe_theme') || 'light';
    document.documentElement.setAttribute('data-theme', theme);
})();

// Fonction globale pour basculer
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = (current === 'dark') ? 'light' : 'dark';
    
    localStorage.setItem('universe_theme', next);
    document.documentElement.setAttribute('data-theme', next);
    
    // Envoyer en session côté serveur (optionnel, pour persistance multi-onglets)
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'toggle_theme.php';
    form.style.display = 'none';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'theme';
    input.value = next;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Met à jour l'icône du bouton
function updateThemeIcon() {
    const btn = document.getElementById('themeToggleBtn');
    if (!btn) return;
    const theme = document.documentElement.getAttribute('data-theme') || 'light';
    btn.textContent = (theme === 'dark') ? '☀️' : '🌙';
    btn.title = (theme === 'dark') ? 'Mode clair' : 'Mode sombre';
}

document.addEventListener('DOMContentLoaded', updateThemeIcon);
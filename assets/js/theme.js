/**
 * Theme Management
 * Dark/Light mode toggle with localStorage persistence
 */

class ThemeManager {
    constructor() {
        this.themeKey = 'backup_manager_theme';
        this.init();
    }
    
    init() {
        // Apply saved theme or detect system preference
        const savedTheme = localStorage.getItem(this.themeKey);
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme) {
            this.setTheme(savedTheme);
        } else if (systemPrefersDark) {
            this.setTheme('dark');
        } else {
            this.setTheme('light');
        }
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem(this.themeKey)) {
                this.setTheme(e.matches ? 'dark' : 'light');
            }
        });
    }
    
    setTheme(theme) {
        const html = document.documentElement;
        
        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
        
        // Update theme toggle button if it exists
        this.updateThemeToggle(theme);
        
        // Save to localStorage
        localStorage.setItem(this.themeKey, theme);
        
        // Dispatch theme change event
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
    }
    
    getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }
    
    toggleTheme() {
        const currentTheme = this.getCurrentTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
        return newTheme;
    }
    
    updateThemeToggle(theme) {
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                if (theme === 'dark') {
                    icon.className = 'bi bi-sun';
                    toggleBtn.title = 'Switch to light mode';
                } else {
                    icon.className = 'bi bi-moon';
                    toggleBtn.title = 'Switch to dark mode';
                }
            }
        }
    }
    
    // Create theme toggle button
    createThemeToggle() {
        const currentTheme = this.getCurrentTheme();
        const iconClass = currentTheme === 'dark' ? 'bi-sun' : 'bi-moon';
        const title = currentTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
        
        return `
            <button id="theme-toggle" class="btn btn-outline-light btn-sm" title="${title}">
                <i class="bi ${iconClass}"></i>
            </button>
        `;
    }
}

// Initialize theme manager
const themeManager = new ThemeManager();

// Add theme toggle to navbar if it doesn't exist
document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.querySelector('.navbar-nav:last-child');
    if (navbar && !document.getElementById('theme-toggle')) {
        const themeToggle = document.createElement('li');
        themeToggle.className = 'nav-item';
        themeToggle.innerHTML = themeManager.createThemeToggle();
        navbar.appendChild(themeToggle);
        
        // Add click event listener
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                themeManager.toggleTheme();
            });
        }
    }
});

// Export for use in other scripts
window.ThemeManager = ThemeManager;
window.themeManager = themeManager;

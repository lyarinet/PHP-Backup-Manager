/**
 * Backup Manager JavaScript
 * Modern, responsive functionality with real-time updates
 */

class BackupManager {
    constructor() {
        this.apiBase = 'api/';
        this.pollInterval = null;
        this.currentTheme = localStorage.getItem('theme') || 'light';
        this.init();
    }
    
    init() {
        this.setupTheme();
        this.setupEventListeners();
        this.setupTooltips();
        this.setupAutoRefresh();
    }
    
    setupTheme() {
        document.documentElement.setAttribute('data-theme', this.currentTheme);
        this.updateThemeToggle();
    }
    
    setupEventListeners() {
        // Theme toggle
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }
        
        // Auto-dismiss alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                this.fadeOut(alert);
            }, 5000);
        });
        
        // Form validation
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => this.validateForm(e));
        });
        
        // Confirm dialogs
        const confirmLinks = document.querySelectorAll('[data-confirm]');
        confirmLinks.forEach(link => {
            link.addEventListener('click', (e) => this.confirmAction(e));
        });
    }
    
    setupTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    setupAutoRefresh() {
        // Auto-refresh dashboard data every 30 seconds
        if (window.location.pathname.includes('dashboard.php')) {
            this.pollInterval = setInterval(() => {
                this.refreshDashboardData();
            }, 30000);
        }
    }
    
    toggleTheme() {
        this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', this.currentTheme);
        localStorage.setItem('theme', this.currentTheme);
        this.updateThemeToggle();
    }
    
    updateThemeToggle() {
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.className = this.currentTheme === 'light' ? 'bi bi-moon' : 'bi bi-sun';
            }
        }
    }
    
    async apiCall(endpoint, options = {}) {
        const url = this.apiBase + endpoint;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        };
        
        const response = await fetch(url, { ...defaultOptions, ...options });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('API Response text:', text);
        
        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('JSON parse error:', error);
            console.error('Response text:', text);
            throw new Error('Invalid JSON response: ' + text.substring(0, 100));
        }
    }
    
    async startBackup(configId) {
        try {
            const result = await this.apiCall('backup.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'start',
                    config_id: configId
                })
            });
            
            if (result.success) {
                this.showToast('Backup started successfully', 'success');
                
                // Debug logging
                console.log('Backup API response:', result);
                console.log('History ID:', result.history_id);
                console.log('showProgressModal function exists:', typeof showProgressModal === 'function');
                
                // Show progress modal if history_id is returned
                if (result.history_id && typeof showProgressModal === 'function') {
                    // Get config name for display
                    const configName = document.querySelector(`[onclick="startBackup(${configId})"]`).closest('.card').querySelector('h6').textContent.trim();
                    console.log('Calling showProgressModal with:', result.history_id, configName);
                    showProgressModal(result.history_id, configName);
                } else {
                    console.log('Not showing progress modal - history_id:', result.history_id, 'function exists:', typeof showProgressModal === 'function');
                }
                
                this.refreshDashboardData();
            } else {
                // Handle authentication error specifically
                if (result.message === 'Authentication required') {
                    this.showToast('Please log in first to use backup features', 'error');
                    // Redirect to login page
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                } else {
                    this.showToast('Failed to start backup: ' + result.message, 'error');
                }
            }
        } catch (error) {
            this.showToast('Error starting backup: ' + error.message, 'error');
        }
    }
    
    async getBackupStatus(historyId) {
        try {
            const result = await this.apiCall(`backup.php?action=status&history_id=${historyId}`);
            return result.backup;
        } catch (error) {
            console.error('Error getting backup status:', error);
            return null;
        }
    }
    
    async refreshDashboardData() {
        try {
            // Refresh recent backups
            const historyResult = await this.apiCall('history.php?action=list&limit=5');
            if (historyResult.success) {
                this.updateRecentBackupsTable(historyResult.history);
            }
            
            // Refresh statistics
            const statsResult = await this.apiCall('history.php?action=stats');
            if (statsResult.success) {
                this.updateStatistics(statsResult.stats);
            }
        } catch (error) {
            console.error('Error refreshing dashboard data:', error);
        }
    }
    
    updateRecentBackupsTable(backups) {
        const tbody = document.querySelector('#recentBackupsTable tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        if (backups.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No backups yet</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        backups.forEach(backup => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${this.escapeHtml(backup.config_name)}</td>
                <td><span class="badge bg-secondary">${backup.backup_type}</span></td>
                <td>${this.getStatusBadge(backup.status)}</td>
                <td>${this.formatBytes(backup.size_bytes)}</td>
                <td>${this.formatDate(backup.start_time)}</td>
            `;
            tbody.appendChild(row);
        });
    }
    
    updateStatistics(stats) {
        // Update total backups
        const totalBackups = document.querySelector('[data-stat="total_backups"]');
        if (totalBackups) {
            totalBackups.textContent = this.formatNumber(stats.total_backups);
        }
        
        // Update success rate
        const successRate = document.querySelector('[data-stat="success_rate"]');
        if (successRate) {
            successRate.textContent = stats.success_rate + '%';
        }
        
        // Update total size
        const totalSize = document.querySelector('[data-stat="total_size"]');
        if (totalSize) {
            totalSize.textContent = this.formatBytes(stats.total_size);
        }
        
        // Update last backup
        const lastBackup = document.querySelector('[data-stat="last_backup"]');
        if (lastBackup) {
            lastBackup.textContent = stats.last_backup ? this.formatDate(stats.last_backup) : 'Never';
        }
    }
    
    getStatusBadge(status) {
        const statusConfig = {
            'completed': { class: 'success', icon: 'check-circle' },
            'failed': { class: 'danger', icon: 'x-circle' },
            'running': { class: 'warning', icon: 'clock' }
        };
        
        const config = statusConfig[status] || { class: 'secondary', icon: 'question-circle' };
        
        return `
            <span class="badge bg-${config.class}">
                <i class="bi bi-${config.icon} me-1"></i>
                ${this.capitalizeFirst(status)}
            </span>
        `;
    }
    
    showToast(message, type = 'info') {
        const toastContainer = this.getOrCreateToastContainer();
        const toastId = 'toast-' + Date.now();
        
        const toastHtml = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <i class="bi bi-${this.getToastIcon(type)} text-${type} me-2"></i>
                    <strong class="me-auto">${this.capitalizeFirst(type)}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${this.escapeHtml(message)}
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 5000
        });
        
        toast.show();
        
        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }
    
    getOrCreateToastContainer() {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }
    
    getToastIcon(type) {
        const icons = {
            'success': 'check-circle',
            'error': 'exclamation-triangle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    validateForm(event) {
        const form = event.target;
        const formData = new FormData(form);
        let isValid = true;
        
        // Clear previous validation
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        
        // Validate required fields
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });
        
        // Validate email fields
        form.querySelectorAll('input[type="email"]').forEach(field => {
            if (field.value && !this.isValidEmail(field.value)) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });
        
        if (!isValid) {
            event.preventDefault();
            this.showToast('Please correct the errors in the form', 'error');
        }
        
        return isValid;
    }
    
    confirmAction(event) {
        const message = event.target.getAttribute('data-confirm');
        if (!confirm(message)) {
            event.preventDefault();
        }
    }
    
    fadeOut(element) {
        element.style.transition = 'opacity 0.5s ease';
        element.style.opacity = '0';
        setTimeout(() => {
            element.remove();
        }, 500);
    }
    
    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    formatNumber(number) {
        return new Intl.NumberFormat().format(number);
    }
    
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
    window.backupManager = new BackupManager();
});

// Global functions for backward compatibility
function showToast(message, type = 'info') {
    if (window.backupManager) {
        window.backupManager.showToast(message, type);
    }
}

function startBackup(configId) {
    if (window.backupManager) {
        window.backupManager.startBackup(configId);
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BackupManager;
}

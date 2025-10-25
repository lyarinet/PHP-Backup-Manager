/**
 * Search and Filter Module
 * Reusable filtering functionality for tables and lists
 */

class FilterManager {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            searchFields: ['name', 'type', 'status'],
            filterFields: ['type', 'status'],
            sortFields: ['name', 'date', 'size'],
            ...options
        };
        
        this.currentFilters = {};
        this.currentSort = { field: null, direction: 'asc' };
        
        this.init();
    }
    
    init() {
        if (!this.container) return;
        
        this.createFilterUI();
        this.bindEvents();
        this.applyFilters();
    }
    
    createFilterUI() {
        const filterContainer = document.createElement('div');
        filterContainer.className = 'filter-container mb-3';
        filterContainer.innerHTML = `
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="search-input" class="form-label">Search</label>
                    <input type="text" id="search-input" class="form-control" placeholder="Search...">
                </div>
                ${this.options.filterFields.map(field => `
                    <div class="col-md-3">
                        <label for="filter-${field}" class="form-label">${this.capitalize(field)}</label>
                        <select id="filter-${field}" class="form-select">
                            <option value="">All ${this.capitalize(field)}s</option>
                        </select>
                    </div>
                `).join('')}
                <div class="col-md-2">
                    <label for="sort-select" class="form-label">Sort by</label>
                    <select id="sort-select" class="form-select">
                        <option value="">Default</option>
                        ${this.options.sortFields.map(field => `
                            <option value="${field}-asc">${this.capitalize(field)} (A-Z)</option>
                            <option value="${field}-desc">${this.capitalize(field)} (Z-A)</option>
                        `).join('')}
                    </select>
                </div>
            </div>
        `;
        
        // Insert before the first child of container
        this.container.insertBefore(filterContainer, this.container.firstChild);
        
        this.populateFilterOptions();
    }
    
    populateFilterOptions() {
        this.options.filterFields.forEach(field => {
            const select = document.getElementById(`filter-${field}`);
            if (!select) return;
            
            // Get unique values from data
            const values = this.getUniqueValues(field);
            values.forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = this.capitalize(value);
                select.appendChild(option);
            });
        });
    }
    
    getUniqueValues(field) {
        const rows = this.container.querySelectorAll('tbody tr, .card, .list-item');
        const values = new Set();
        
        rows.forEach(row => {
            const value = this.extractFieldValue(row, field);
            if (value) values.add(value);
        });
        
        return Array.from(values).sort();
    }
    
    extractFieldValue(element, field) {
        // Try different selectors based on field name
        const selectors = {
            'type': '[data-type], .badge, .type',
            'status': '[data-status], .status, .badge',
            'name': '[data-name], .name, h5, h6',
            'date': '[data-date], .date, time'
        };
        
        const selector = selectors[field] || `[data-${field}]`;
        const element = element.querySelector(selector);
        
        if (element) {
            return element.textContent.trim() || element.getAttribute(`data-${field}`);
        }
        
        return null;
    }
    
    bindEvents() {
        // Search input
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                this.currentFilters.search = searchInput.value;
                this.applyFilters();
            });
        }
        
        // Filter selects
        this.options.filterFields.forEach(field => {
            const select = document.getElementById(`filter-${field}`);
            if (select) {
                select.addEventListener('change', () => {
                    this.currentFilters[field] = select.value;
                    this.applyFilters();
                });
            }
        });
        
        // Sort select
        const sortSelect = document.getElementById('sort-select');
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                const value = sortSelect.value;
                if (value) {
                    const [field, direction] = value.split('-');
                    this.currentSort = { field, direction };
                } else {
                    this.currentSort = { field: null, direction: 'asc' };
                }
                this.applyFilters();
            });
        }
    }
    
    applyFilters() {
        const rows = this.container.querySelectorAll('tbody tr, .card, .list-item');
        
        rows.forEach(row => {
            let show = true;
            
            // Apply search filter
            if (this.currentFilters.search) {
                const searchText = this.currentFilters.search.toLowerCase();
                const rowText = row.textContent.toLowerCase();
                show = show && rowText.includes(searchText);
            }
            
            // Apply field filters
            this.options.filterFields.forEach(field => {
                if (this.currentFilters[field]) {
                    const rowValue = this.extractFieldValue(row, field);
                    show = show && rowValue === this.currentFilters[field];
                }
            });
            
            // Show/hide row
            row.style.display = show ? '' : 'none';
        });
        
        // Apply sorting
        if (this.currentSort.field) {
            this.sortRows();
        }
        
        // Update results count
        this.updateResultsCount();
    }
    
    sortRows() {
        const tbody = this.container.querySelector('tbody');
        if (!tbody) return;
        
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            const aValue = this.extractFieldValue(a, this.currentSort.field);
            const bValue = this.extractFieldValue(b, this.currentSort.field);
            
            if (aValue === bValue) return 0;
            
            const comparison = aValue < bValue ? -1 : 1;
            return this.currentSort.direction === 'desc' ? -comparison : comparison;
        });
        
        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
    }
    
    updateResultsCount() {
        const visibleRows = this.container.querySelectorAll('tbody tr:not([style*="display: none"]), .card:not([style*="display: none"])');
        const totalRows = this.container.querySelectorAll('tbody tr, .card');
        
        // Update or create results count
        let countElement = document.getElementById('results-count');
        if (!countElement) {
            countElement = document.createElement('div');
            countElement.id = 'results-count';
            countElement.className = 'text-muted small mt-2';
            this.container.querySelector('.filter-container').appendChild(countElement);
        }
        
        countElement.textContent = `Showing ${visibleRows.length} of ${totalRows.length} results`;
    }
    
    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // Public methods
    clearFilters() {
        this.currentFilters = {};
        this.currentSort = { field: null, direction: 'asc' };
        
        // Reset form elements
        document.getElementById('search-input').value = '';
        this.options.filterFields.forEach(field => {
            const select = document.getElementById(`filter-${field}`);
            if (select) select.value = '';
        });
        document.getElementById('sort-select').value = '';
        
        this.applyFilters();
    }
    
    getActiveFilters() {
        return { ...this.currentFilters };
    }
    
    getActiveSort() {
        return { ...this.currentSort };
    }
}

// Date range picker functionality
class DateRangeFilter {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            dateFormat: 'YYYY-MM-DD',
            ...options
        };
        
        this.init();
    }
    
    init() {
        if (!this.container) return;
        
        this.createDateRangeUI();
        this.bindEvents();
    }
    
    createDateRangeUI() {
        const dateContainer = document.createElement('div');
        dateContainer.className = 'date-range-filter mb-3';
        dateContainer.innerHTML = `
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="date-from" class="form-label">From Date</label>
                    <input type="date" id="date-from" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="date-to" class="form-label">To Date</label>
                    <input type="date" id="date-to" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="button" id="clear-dates" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-x-circle me-1"></i>Clear Dates
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        this.container.insertBefore(dateContainer, this.container.firstChild);
    }
    
    bindEvents() {
        const fromInput = document.getElementById('date-from');
        const toInput = document.getElementById('date-to');
        const clearBtn = document.getElementById('clear-dates');
        
        if (fromInput) {
            fromInput.addEventListener('change', () => this.applyDateFilter());
        }
        
        if (toInput) {
            toInput.addEventListener('change', () => this.applyDateFilter());
        }
        
        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearDates());
        }
    }
    
    applyDateFilter() {
        const fromDate = document.getElementById('date-from').value;
        const toDate = document.getElementById('date-to').value;
        
        const rows = this.container.querySelectorAll('tbody tr, .card');
        
        rows.forEach(row => {
            let show = true;
            
            if (fromDate || toDate) {
                const rowDate = this.extractDate(row);
                if (rowDate) {
                    if (fromDate && rowDate < fromDate) show = false;
                    if (toDate && rowDate > toDate) show = false;
                } else {
                    show = false;
                }
            }
            
            row.style.display = show ? '' : 'none';
        });
    }
    
    extractDate(element) {
        // Try to find date in various formats
        const dateSelectors = [
            '[data-date]',
            '.date',
            'time',
            'td:nth-child(2)', // Common table date column
            'td:nth-child(3)'
        ];
        
        for (const selector of dateSelectors) {
            const dateElement = element.querySelector(selector);
            if (dateElement) {
                const dateText = dateElement.textContent.trim();
                const date = new Date(dateText);
                if (!isNaN(date.getTime())) {
                    return date.toISOString().split('T')[0];
                }
            }
        }
        
        return null;
    }
    
    clearDates() {
        document.getElementById('date-from').value = '';
        document.getElementById('date-to').value = '';
        this.applyDateFilter();
    }
}

// Export for use in other scripts
window.FilterManager = FilterManager;
window.DateRangeFilter = DateRangeFilter;

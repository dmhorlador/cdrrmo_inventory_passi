// CDRRMO Inventory System - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
    
    // Add loading indicator to all forms on submit
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Get submit button
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.classList.contains('no-loading')) {
                // Disable submit button and show loading
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.style.opacity = '0.6';
                
                // Show global loading indicator
                showFormLoading();
                
                // Store original text for potential restoration
                submitBtn.dataset.originalText = originalText;
            }
        });
    });
    
    // Confirm delete actions with loading
    const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            } else {
                // Show loading indicator on delete
                showFormLoading('Deleting...');
                button.disabled = true;
                button.style.opacity = '0.6';
            }
        });
    });
    
    // Number formatting
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });
    });
    
    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
    
    // Logout confirmation with SweetAlert
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Logout',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1a3370',
                cancelButtonColor: '#dc2626',
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show custom logo loading overlay
                    showLogoLoading();
                    
                    // Wait 2 seconds then redirect to logout
                    setTimeout(() => {
                        window.location.href = 'logout.php';
                    }, 2000);
                }
            });
        });
    }
    
    // Table row click highlighting
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON') {
                this.classList.toggle('selected');
            }
        });
    });
});

// Format number with commas
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Format currency
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Show form loading overlay
function showFormLoading(text = 'Processing...', showSpinner = true) {
    // Remove any existing loading overlay
    const existing = document.getElementById('formLoadingOverlay');
    if (existing) {
        existing.remove();
    }
    
    const overlay = document.createElement('div');
    overlay.className = 'form-loading-overlay';
    overlay.id = 'formLoadingOverlay';
    
    overlay.innerHTML = `
        <div class="form-loading-container">
            ${showSpinner ? '<div class="spinner-inline"></div>' : ''}
            <div class="form-loading-text">${text}</div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Prevent interaction with the page
    overlay.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    });
    
    return overlay;
}

// Hide form loading overlay
function hideFormLoading() {
    const overlay = document.getElementById('formLoadingOverlay');
    if (overlay) {
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.3s ease';
        setTimeout(() => {
            overlay.remove();
        }, 300);
    }
}

// Show loading spinner
function showLoading() {
    const loader = document.createElement('div');
    loader.className = 'spinner';
    loader.id = 'globalLoader';
    document.body.appendChild(loader);
}

// Hide loading spinner
function hideLoading() {
    const loader = document.getElementById('globalLoader');
    if (loader) {
        loader.remove();
    }
}

// Show custom logo loading overlay
function showLogoLoading(text = 'Logging out...', subtext = 'Please wait') {
    const overlay = document.createElement('div');
    overlay.className = 'logo-loading-overlay';
    overlay.id = 'logoLoadingOverlay';
    
    overlay.innerHTML = `
        <div class="logo-loading-container">
            <div class="logo-loading-spinner">
                <img src="images/logo2.png" alt="CDRRMO Logo">
            </div>
            <div class="logo-loading-text">${text}</div>
            <div class="logo-loading-subtext">${subtext}</div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    return overlay;
}

// Hide custom logo loading overlay
function hideLogoLoading() {
    const overlay = document.getElementById('logoLoadingOverlay');
    if (overlay) {
        overlay.style.animation = 'fadeInOverlay 0.3s ease-out reverse';
        setTimeout(() => {
            overlay.remove();
        }, 300);
    }
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.5s ease';
        setTimeout(() => {
            notification.remove();
        }, 500);
    }, 3000);
}

// Print function
function printTable(tableId) {
    const printWindow = window.open('', '', 'height=600,width=800');
    const table = document.getElementById(tableId);
    
    printWindow.document.write('<html><head><title>Print</title>');
    printWindow.document.write('<link rel="stylesheet" href="css/style.css">');
    printWindow.document.write('<style>@media print { body { padding: 20px; } }</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h1>CDRRMO Inventory System</h1>');
    printWindow.document.write('<h2>Passi City</h2>');
    printWindow.document.write('<hr>');
    printWindow.document.write(table.outerHTML);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.print();
}

// Export to CSV
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        cols.forEach(col => {
            csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
        });
        csv.push(csvRow.join(','));
    });
    
    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Form validation helper
function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('error');
            isValid = false;
        } else {
            input.classList.remove('error');
        }
    });
    
    return isValid;
}

// Date formatting
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

// Calculate stock percentage
function calculateStockPercentage(current, max) {
    if (max === 0) return 0;
    return Math.round((current / max) * 100);
}
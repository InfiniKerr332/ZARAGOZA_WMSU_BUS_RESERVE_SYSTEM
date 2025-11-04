// Auto-close alert messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
    
    // Alert close button
    const closeButtons = document.querySelectorAll('.alert-close');
    closeButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            this.parentElement.style.opacity = '0';
            setTimeout(function() {
                btn.parentElement.style.display = 'none';
            }, 300);
        });
    });
});

// Show loading spinner
function showLoading() {
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'loading';
    loadingDiv.id = 'loading-spinner';
    document.body.appendChild(loadingDiv);
}

// Hide loading spinner
function hideLoading() {
    const loadingDiv = document.getElementById('loading-spinner');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}

// Format date input to prevent past dates
function setMinDate(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        const today = new Date().toISOString().split('T')[0];
        input.setAttribute('min', today);
    }
}

// Disable Sundays in date picker
function disableSundays(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.addEventListener('input', function() {
            const date = new Date(this.value);
            if (date.getDay() === 0) { // Sunday
                alert('Sorry, reservations on Sundays are not allowed. Please choose a working day (Monday-Saturday).');
                this.value = '';
            }
        });
    }
}
// Confirm before form submission
function confirmSubmit(message) {
    return confirm(message || 'Are you sure you want to submit this form?');
}

// Confirm before deletion
function confirmDelete(itemName) {
    return confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`);
}

// Confirm before cancellation
function confirmCancel(itemName) {
    return confirm(`Are you sure you want to cancel ${itemName}?`);
}

// Confirm before approval
function confirmApprove(itemName) {
    return confirm(`Are you sure you want to approve ${itemName}?`);
}

// Confirm before rejection
function confirmReject(itemName) {
    return confirm(`Are you sure you want to reject ${itemName}?`);
}

// Confirm before update
function confirmUpdate() {
    return confirm('Are you sure you want to save these changes?');
}

// Add confirmation to forms with class 'confirm-submit'
document.addEventListener('DOMContentLoaded', function() {
    const formsWithConfirm = document.querySelectorAll('form.confirm-submit');
    
    formsWithConfirm.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const message = this.getAttribute('data-confirm-message') || 'Are you sure you want to submit this form?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Add confirmation to delete links
    const deleteLinks = document.querySelectorAll('a[data-action="delete"]');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            const itemName = this.getAttribute('data-item-name') || 'this item';
            if (!confirmDelete(itemName)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Add confirmation to cancel links
    const cancelLinks = document.querySelectorAll('a[data-action="cancel"]');
    cancelLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            const itemName = this.getAttribute('data-item-name') || 'this reservation';
            if (!confirmCancel(itemName)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Add confirmation to approve buttons
    const approveButtons = document.querySelectorAll('button[data-action="approve"]');
    approveButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const itemName = this.getAttribute('data-item-name') || 'this item';
            if (!confirmApprove(itemName)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Add confirmation to reject buttons
    const rejectButtons = document.querySelectorAll('button[data-action="reject"]');
    rejectButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const itemName = this.getAttribute('data-item-name') || 'this item';
            if (!confirmReject(itemName)) {
                e.preventDefault();
                return false;
            }
        });
    });
});
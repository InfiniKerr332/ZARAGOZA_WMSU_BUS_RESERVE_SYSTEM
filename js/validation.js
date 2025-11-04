// Password validation
function validatePassword(password) {
    const errors = [];
    
    if (password.length < 8) {
        errors.push('Password must be at least 8 characters long');
    }
    
    if (!/[A-Z]/.test(password)) {
        errors.push('Password must contain at least one uppercase letter');
    }
    
    if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
        errors.push('Password must contain at least one special character');
    }
    
    return errors;
}

// Email validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Phone number validation (Philippine format)
function validatePhone(phone) {
    const re = /^(09|\+639)\d{9}$/;
    return re.test(phone.replace(/\s+/g, ''));
}

// Real-time password validation
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const errors = validatePassword(this.value);
            const errorSpan = this.parentElement.querySelector('.error-text');
            
            if (errors.length > 0 && this.value.length > 0) {
                if (errorSpan) {
                    errorSpan.textContent = errors.join('. ');
                }
            } else {
                if (errorSpan) {
                    errorSpan.textContent = '';
                }
            }
        });
    }
    
    if (confirmPasswordInput && passwordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const errorSpan = this.parentElement.querySelector('.error-text');
            
            if (this.value !== passwordInput.value && this.value.length > 0) {
                if (errorSpan) {
                    errorSpan.textContent = 'Passwords do not match';
                }
            } else {
                if (errorSpan) {
                    errorSpan.textContent = '';
                }
            }
        });
    }
    
    // Email validation
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const errorSpan = this.parentElement.querySelector('.error-text');
            
            if (!validateEmail(this.value) && this.value.length > 0) {
                if (errorSpan) {
                    errorSpan.textContent = 'Please enter a valid email address';
                }
            }
        });
    }
    
    // Phone validation
    const phoneInput = document.getElementById('contact_no');
    if (phoneInput) {
        phoneInput.addEventListener('blur', function() {
            const errorSpan = this.parentElement.querySelector('.error-text');
            
            if (!validatePhone(this.value) && this.value.length > 0) {
                if (errorSpan) {
                    errorSpan.textContent = 'Please enter a valid Philippine phone number (09XXXXXXXXX)';
                }
            }
        });
    }
});
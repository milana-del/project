// Form handling with fetch and validation
class FormHandler {
    constructor() {
        this.forms = [];
        this.init();
    }

    init() {
        this.setupForms();
    }

    setupForms() {
        const mainForm = document.getElementById('main-contact-form');
        const modalForm = document.getElementById('modal-contact-form');

        if (mainForm) {
            this.forms.push({
                element: mainForm,
                type: 'main'
            });
        }

        if (modalForm) {
            this.forms.push({
                element: modalForm,
                type: 'modal'
            });
        }

        this.forms.forEach(formConfig => {
            this.setupFormEventListener(formConfig);
        });
    }

    setupFormEventListener(formConfig) {
        const form = formConfig.element;
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleFormSubmit(form, formConfig.type);
        });

        // Real-time validation
        form.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }

    async handleFormSubmit(form, formType) {
        const submitBtn = form.querySelector('.submit-btn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        const formData = new FormData(form);

        // Validate form before submission
        if (!this.validateForm(form)) {
            return;
        }

        // Show loading state
        submitBtn.disabled = true;
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');

        try {
            const data = Object.fromEntries(formData.entries());
            
            // Simulate API call - replace with actual endpoint
            const response = await this.submitToServer(data);
            
            if (response.success) {
                this.showSuccessMessage(form, 'Сообщение успешно отправлено!');
                form.reset();
                
                // Clear localStorage on successful submission
                if (formType === 'modal') {
                    localStorage.removeItem('contactFormData');
                }
                
                // Close modal after successful submission
                if (formType === 'modal') {
                    setTimeout(() => {
                        document.getElementById('contactModal').classList.remove('active');
                    }, 2000);
                }
            } else {
                throw new Error(response.message || 'Ошибка отправки');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showErrorMessage(form, 'Ошибка при отправке формы. Пожалуйста, попробуйте еще раз.');
        } finally {
            // Restore button state
            submitBtn.disabled = false;
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
        }
    }

    async submitToServer(data) {
        // Replace this with your actual form submission endpoint
        // Example using Formcarry or similar service
        const response = await fetch('https://formcarry.com/s/AJYK7wadVOb', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    validateForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        // Clear previous error
        this.clearFieldError(field);

        // Required field validation
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'Это поле обязательно для заполнения';
        }

        // Email validation
        if (field.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Введите корректный email адрес';
            }
        }

        // Phone validation
        if (field.name === 'phone' && value) {
            const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
            if (!phoneRegex.test(value.replace(/\s/g, ''))) {
                isValid = false;
                errorMessage = 'Введите корректный номер телефона';
            }
        }

        // Message length validation
        if (field.name === 'message' && value.length < 10) {
            isValid = false;
            errorMessage = 'Сообщение должно содержать минимум 10 символов';
        }

        if (!isValid) {
            this.showFieldError(field, errorMessage);
        }

        return isValid;
    }

    showFieldError(field, message) {
        field.classList.add('error');
        
        let errorElement = field.parentNode.querySelector('.field-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }

    clearFieldError(field) {
        field.classList.remove('error');
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    }

    showSuccessMessage(form, message) {
        this.showMessage(form, message, 'success');
    }

    showErrorMessage(form, message) {
        this.showMessage(form, message, 'error');
    }

    showMessage(form, message, type) {
        // Remove existing message
        const existingMessage = form.querySelector('.form-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        // Create new message
        const messageElement = document.createElement('div');
        messageElement.className = `form-message ${type}`;
        messageElement.textContent = message;
        
        form.insertBefore(messageElement, form.querySelector('.submit-btn'));

        // Auto-remove success messages
        if (type === 'success') {
            setTimeout(() => {
                messageElement.remove();
            }, 5000);
        }
    }
}

// Add CSS for form validation
const formStyles = `
    .field-error {
        color: #ff6b6b;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        display: none;
    }
    
    input.error,
    textarea.error {
        border-color: #ff6b6b !important;
        box-shadow: 0 0 0 2px rgba(255, 107, 107, 0.2) !important;
    }
    
    .form-message {
        padding: 1rem;
        border-radius: var(--border-radius);
        margin-bottom: 1rem;
        text-align: center;
        font-weight: 600;
    }
    
    .form-message.success {
        background: rgba(76, 175, 80, 0.1);
        color: #4caf50;
        border: 1px solid #4caf50;
    }
    
    .form-message.error {
        background: rgba(244, 67, 54, 0.1);
        color: #f44336;
        border: 1px solid #f44336;
    }
`;

// Inject styles
const styleSheet = document.createElement('style');
styleSheet.textContent = formStyles;
document.head.appendChild(styleSheet);

// Initialize form handler
document.addEventListener('DOMContentLoaded', () => {
    new FormHandler();
});
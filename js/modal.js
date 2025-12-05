// Modal functionality with animations
class ContactModal {
    constructor() {
        this.modal = document.getElementById('contactModal');
        this.contactBtn = document.getElementById('contact-btn');
        this.heroContactBtn = document.getElementById('hero-contact-btn');
        this.modalClose = document.getElementById('modalClose');
        this.isAnimating = false;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupLocalStorage();
    }

    setupEventListeners() {
        // Open modal buttons
        this.contactBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.openModal();
        });

        this.heroContactBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.openModal();
        });

        // Close modal
        this.modalClose.addEventListener('click', () => this.closeModal());

        // Close modal on background click
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.closeModal();
            }
        });
    }

    setupLocalStorage() {
        // Restore form data from localStorage
        const savedData = localStorage.getItem('contactFormData');
        if (savedData) {
            try {
                const formData = JSON.parse(savedData);
                this.restoreFormData(formData);
            } catch (e) {
                console.error('Error restoring form data:', e);
            }
        }

        // Save form data on input
        const form = document.getElementById('modal-contact-form');
        form.addEventListener('input', () => this.saveFormData());
    }

    openModal() {
        if (this.isAnimating) return;
        
        this.isAnimating = true;
        this.modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
        
        // Add to browser history
        window.history.pushState({ modalOpen: true }, '', '#contact-modal');
        
        setTimeout(() => {
            this.isAnimating = false;
            // Focus on first input
            const firstInput = this.modal.querySelector('input, textarea');
            if (firstInput) firstInput.focus();
        }, 300);
    }

    closeModal() {
        if (this.isAnimating) return;
        
        this.isAnimating = true;
        this.modal.classList.add('closing');
        
        setTimeout(() => {
            this.modal.classList.remove('active', 'closing');
            document.body.style.overflow = ''; // Restore scrolling
            this.isAnimating = false;
            
            // Remove from browser history if we added it
            if (window.history.state?.modalOpen) {
                window.history.back();
            }
        }, 300);
    }

    saveFormData() {
        const form = document.getElementById('modal-contact-form');
        const formData = {
            name: form.querySelector('#modal-name').value,
            email: form.querySelector('#modal-email').value,
            phone: form.querySelector('#modal-phone').value,
            message: form.querySelector('#modal-message').value
        };
        
        localStorage.setItem('contactFormData', JSON.stringify(formData));
    }

    restoreFormData(formData) {
        const form = document.getElementById('modal-contact-form');
        if (formData.name) form.querySelector('#modal-name').value = formData.name;
        if (formData.email) form.querySelector('#modal-email').value = formData.email;
        if (formData.phone) form.querySelector('#modal-phone').value = formData.phone;
        if (formData.message) form.querySelector('#modal-message').value = formData.message;
    }

    clearFormData() {
        localStorage.removeItem('contactFormData');
        document.getElementById('modal-contact-form').reset();
    }
}

// Initialize modal
document.addEventListener('DOMContentLoaded', () => {
    new ContactModal();
});
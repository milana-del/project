// Обработка формы связи – только для авторизованных
document.addEventListener('DOMContentLoaded', () => {
    const mainForm = document.getElementById('main-contact-form');
    const modalForm = document.getElementById('modal-contact-form');

    async function checkAuth() {
        try {
            const res = await fetch('/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'profile' })
            });
            if (res.ok) return true;
            return false;
        } catch(e) {
            return false;
        }
    }

    async function sendMessage(formData, formElement) {
        const submitBtn = formElement.querySelector('.submit-btn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        submitBtn.disabled = true;
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');

        try {
            const res = await fetch('/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            const data = await res.json();
            if (res.ok) {
                alert('Сообщение отправлено!');
                formElement.reset();
                if (formElement.id === 'modal-contact-form') {
                    const modal = document.getElementById('contactModal');
                    if (modal) modal.classList.remove('active');
                }
            } else {
                let err = data.error || data.errors?.message || 'Ошибка отправки';
                alert('Ошибка: ' + err);
            }
        } catch(e) {
            alert('Ошибка сети');
        } finally {
            submitBtn.disabled = false;
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
        }
    }

    function savePendingMessage(data) {
        localStorage.setItem('pending_message', JSON.stringify(data));
    }

    async function handleFormSubmit(e, form) {
        e.preventDefault();
        // Проверяем согласие
        const privacy = form.querySelector('input[name="privacy"]');
        if (privacy && !privacy.checked) {
            const errorDiv = form.querySelector('.field-error');
            if (errorDiv) errorDiv.style.display = 'block';
            return;
        }
        // Собираем данные: subject, message, privacy
        const subject = form.querySelector('[name="subject"]')?.value || '';
        const message = form.querySelector('[name="message"]')?.value || '';
        const data = {
            action: 'message',
            subject: subject,
            message: message,
            privacy: true
        };

        const isAuth = await checkAuth();
        if (!isAuth) {
            savePendingMessage(data);
            window.location.href = 'fan_login.php?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        await sendMessage(data, form);
    }

    if (mainForm) mainForm.addEventListener('submit', (e) => handleFormSubmit(e, mainForm));
    if (modalForm) modalForm.addEventListener('submit', (e) => handleFormSubmit(e, modalForm));
});
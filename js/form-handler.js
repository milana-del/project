// form-handler.js
document.addEventListener('DOMContentLoaded', () => {
    const mainForm = document.getElementById('main-contact-form');
    const modalForm = document.getElementById('modal-contact-form');

    if (!mainForm && !modalForm) return;

    async function checkAuth() {
        try {
            const res = await fetch(API_BASE + '?action=profile', { method: 'GET' });
            return res.ok;
        } catch(e) {
            return false;
        }
    }

    async function sendMessage(formData, formElement) {
        const submitBtn = formElement.querySelector('.submit-btn');
        if (!submitBtn) return;
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        submitBtn.disabled = true;
        if (btnText) btnText.classList.add('d-none');
        if (btnLoading) btnLoading.classList.remove('d-none');

        try {
            const url = API_BASE + '?action=message&data=' + encodeURIComponent(JSON.stringify(formData));
            const res = await fetch(url, { method: 'GET' });
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
            alert('Ошибка сети. Проверьте соединение.');
            console.error(e);
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.classList.remove('d-none');
            if (btnLoading) btnLoading.classList.add('d-none');
        }
    }

    function savePendingMessage(data) {
        localStorage.setItem('pending_message', JSON.stringify(data));
    }

    async function handleFormSubmit(e, form) {
        e.preventDefault();
        const privacy = form.querySelector('input[name="privacy"]');
        if (privacy && !privacy.checked) {
            const errorDiv = form.querySelector('.field-error');
            if (errorDiv) errorDiv.style.display = 'block';
            return;
        }
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
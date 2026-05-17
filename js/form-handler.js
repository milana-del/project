// Обработка форм с отправкой на наш REST API
document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('#main-contact-form, #modal-contact-form');

    async function submitForm(form, data) {
        const submitBtn = form.querySelector('.submit-btn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        submitBtn.disabled = true;
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');

        try {
            const response = await fetch('/api/messages', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (response.ok) {
                if (result.login && result.password) {
                    alert(`✅ Форма отправлена!\nВаш логин: ${result.login}\nПароль: ${result.password}\nСохраните их для входа в личный кабинет.`);
                } else {
                    alert('Сообщение успешно отправлено!');
                }
                form.reset();
                if (form.id === 'modal-contact-form') {
                    const modal = document.getElementById('contactModal');
                    if (modal) modal.classList.remove('active');
                }
            } else {
                let errorMsg = 'Ошибка отправки.';
                if (result.errors) {
                    errorMsg = Object.values(result.errors).join('\n');
                } else if (result.error) {
                    errorMsg = result.error;
                }
                alert('Ошибка: ' + errorMsg);
            }
        } catch (err) {
            console.error(err);
            alert('Ошибка сети. Попробуйте позже.');
        } finally {
            submitBtn.disabled = false;
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
        }
    }

    forms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            // Валидация чекбокса
            const privacy = form.querySelector('input[name="privacy"]');
            if (privacy && !privacy.checked) {
                const errorDiv = form.querySelector('.field-error');
                if (errorDiv) errorDiv.style.display = 'block';
                return;
            }
            // Сбор данных
            const formData = new FormData(form);
            const data = {};
            formData.forEach((val, key) => {
                if (key !== 'privacy') data[key] = val;
            });
            data.privacy = true;
            // Вызов отправки
            await submitForm(form, data);
        });
    });
});
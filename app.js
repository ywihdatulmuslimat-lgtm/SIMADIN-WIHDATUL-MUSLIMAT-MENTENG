document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-confirm]');
    if (trigger && !window.confirm(trigger.dataset.confirm || 'Lanjutkan tindakan ini?')) {
        event.preventDefault();
        event.stopPropagation();
    }
});

document.addEventListener('input', event => {
    if (event.target.matches('[data-age-from]')) {
        const output = document.querySelector(event.target.dataset.ageFrom);
        if (!output) return;
        const birthDate = new Date(`${event.target.value}T00:00:00`);
        if (Number.isNaN(birthDate.getTime())) {
            output.value = '';
            return;
        }
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const beforeBirthday = today.getMonth() < birthDate.getMonth()
            || (today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate());
        if (beforeBirthday) age--;
        output.value = age >= 0 ? age : '';
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-age-from]').forEach(input => {
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
});

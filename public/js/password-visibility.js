document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    const showIcon = button.querySelector('[data-password-show]');
    const hideIcon = button.querySelector('[data-password-hide]');

    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        showIcon.classList.toggle('hidden', visible);
        hideIcon.classList.toggle('hidden', !visible);
    });
});

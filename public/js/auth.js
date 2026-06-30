document.addEventListener('DOMContentLoaded', function () {
    const toggleButtons = document.querySelectorAll('[data-toggle-password]');

    toggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const inputId = this.getAttribute('data-toggle-password');
            const input = document.getElementById(inputId);

            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                this.textContent = 'Hide';
                this.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                this.textContent = 'Show';
                this.setAttribute('aria-label', 'Show password');
            }
        });
    });
});
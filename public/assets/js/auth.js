(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Tombol tampilkan/sembunyikan password.
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.querySelector(button.getAttribute('data-toggle-password'));
                if (!input) { return; }

                var reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';

                var icon = button.querySelector('i');
                if (icon) { icon.className = reveal ? 'bi bi-eye-slash' : 'bi bi-eye'; }
                button.setAttribute('aria-label', reveal ? 'Sembunyikan password' : 'Tampilkan password');
            });
        });

        // Cegah submit ganda.
        document.querySelectorAll('form[data-once]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('button[type="submit"]');
                if (!button) { return; }
                button.disabled = true;
                button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');
            });
        });

        // Tombol Back di browser: aktifkan lagi tombol yang sempat dinonaktifkan.
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) { return; }
            document.querySelectorAll('form[data-once] button[type="submit"]').forEach(function (button) {
                button.disabled = false;
                var spinner = button.querySelector('.spinner-border');
                if (spinner) { spinner.remove(); }
            });
        });
    });
})();

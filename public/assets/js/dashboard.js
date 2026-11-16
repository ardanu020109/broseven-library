(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Modal konfirmasi hapus: isi nama dan tujuan form dari tombol pemicu.
        var modal = document.getElementById('deleteModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                if (!trigger) { return; }

                modal.querySelector('form').setAttribute('action', trigger.getAttribute('data-delete-url'));
                modal.querySelector('[data-delete-name]').textContent = trigger.getAttribute('data-delete-name') || '';
            });
        }

        // Filter daftar: kirim formulir otomatis saat pilihan berubah.
        document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.form) { select.form.submit(); }
            });
        });

        // Cegah submit ganda pada form bertanda data-once.
        document.querySelectorAll('form[data-once]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('button[type="submit"]');
                if (!button) { return; }
                button.disabled = true;
                button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');
            });
        });

        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) { return; }
            document.querySelectorAll('form[data-once] button[type="submit"]').forEach(function (button) {
                button.disabled = false;
                var spinner = button.querySelector('.spinner-border');
                if (spinner) { spinner.remove(); }
            });
        });

        // Pratinjau gambar sebelum diunggah.
        document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
            input.addEventListener('change', function () {
                var target = document.querySelector(input.getAttribute('data-preview'));
                var file = input.files && input.files[0];
                if (!target || !file || file.type.indexOf('image/') !== 0) { return; }

                target.src = URL.createObjectURL(file);
                target.classList.remove('d-none');
            });
        });

        // Pesan sukses hilang otomatis.
        document.querySelectorAll('.alert-success').forEach(function (alert) {
            setTimeout(function () {
                if (window.bootstrap && document.body.contains(alert)) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            }, 5000);
        });
    });
})();

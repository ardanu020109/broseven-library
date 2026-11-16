(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Filter katalog: kirim formulir otomatis saat pilihan berubah.
        document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.form) { select.form.submit(); }
            });
        });
    });
})();

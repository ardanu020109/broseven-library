(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var period = document.getElementById('period');
        var fields = document.querySelectorAll('.range-field');
        if (!period) { return; }

        // Kolom tanggal hanya relevan untuk periode "Rentang tanggal".
        function toggle() {
            var show = period.value === 'range';
            fields.forEach(function (field) { field.classList.toggle('d-none', !show); });
        }

        period.addEventListener('change', toggle);
        toggle();
    });
})();

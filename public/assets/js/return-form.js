(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('returnForm');
        if (!form) { return; }

        var inputs = form.querySelectorAll('.return-qty');
        var total = document.getElementById('fineTotal');
        var submit = document.getElementById('returnSubmit');
        var rupiah = new Intl.NumberFormat('id-ID');

        // Hanya pratinjau. Nominal denda yang sebenarnya dihitung ulang oleh server saat disimpan.
        function update() {
            var sum = 0;
            var qty = 0;

            inputs.forEach(function (input) {
                var value = Math.max(0, Math.min(parseInt(input.value, 10) || 0, parseInt(input.max, 10) || 0));
                sum += value * (parseFloat(input.dataset.fine) || 0);
                qty += value;
            });

            total.textContent = 'Rp' + rupiah.format(sum);
            submit.disabled = qty === 0;
        }

        inputs.forEach(function (input) { input.addEventListener('input', update); });
        update();
    });
})();

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('borrowForm');
        if (!form) { return; }

        var max = parseInt(form.dataset.max, 10) || 1;
        var blocked = form.dataset.blocked === '1';
        var boxes = form.querySelectorAll('input[name="books[]"]:not([data-locked])');
        var counter = document.getElementById('selectedCount');
        var submit = document.getElementById('borrowSubmit');

        function update() {
            var checked = form.querySelectorAll('input[name="books[]"]:checked').length;
            counter.textContent = checked;
            submit.disabled = blocked || checked === 0;

            // Setelah batas tercapai, pilihan lain dinonaktifkan sementara.
            boxes.forEach(function (box) {
                if (!box.checked) { box.disabled = blocked || checked >= max; }
            });
        }

        boxes.forEach(function (box) { box.addEventListener('change', update); });
        update();
    });
})();

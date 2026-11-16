(function () {
    'use strict';

    function cssColor(name, fallback) {
        var value = getComputedStyle(document.documentElement).getPropertyValue('--bsl-' + name).trim();
        return value || fallback;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') { return; }

        var palette = {
            crimson: cssColor('crimson', '#B22234'),
            sienna: cssColor('sienna', '#A0522D'),
            dark: cssColor('dark-red', '#8B0000')
        };

        Chart.defaults.font.family = '"Segoe UI", system-ui, -apple-system, Arial, sans-serif';
        Chart.defaults.color = '#6f625c';

        document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
            var payload;
            try { payload = JSON.parse(canvas.dataset.chart); } catch (e) { return; }

            var type = canvas.dataset.chartType === 'line' ? 'line' : 'bar';
            var color = palette[canvas.dataset.chartColor] || palette.crimson;

            new Chart(canvas, {
                type: type,
                data: {
                    labels: payload.labels,
                    datasets: [{
                        label: payload.label,
                        data: payload.values,
                        borderColor: color,
                        backgroundColor: type === 'line' ? color + '26' : color,
                        borderWidth: 2,
                        borderRadius: type === 'bar' ? 6 : 0,
                        tension: 0.3,
                        fill: type === 'line',
                        pointRadius: type === 'line' ? 4 : 0,
                        pointBackgroundColor: color
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        });
    });
})();

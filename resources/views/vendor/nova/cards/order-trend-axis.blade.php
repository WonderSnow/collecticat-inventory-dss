<div class="card p-6 mb-4" style="background-color: #111827; border-radius: 8px; border: 1px solid #1f2937; grid-column: span 12 / span 12;">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-white">Transaction Volume</h3>
    </div>
    <div style="position: relative; height: 380px; width: 100%;">
        <canvas id="customOrderTrendCanvas"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    fetch('/api/system-order-trend')
        .then(res => res.json())
        .then(data => {
            const canvas = document.getElementById('customOrderTrendCanvas');
            if(!canvas) return;
            const ctx = canvas.getContext('2d');

            const gradient = ctx.createLinearGradient(0, 0, 0, 380);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

            if (window.myTrendChart) {
                window.myTrendChart.destroy();
            }

            window.myTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Transaction Volume',
                        data: data.sales,
                        borderColor: '#3b82f6',
                        backgroundColor: gradient,
                        fill: true,
                        borderWidth: 3,
                        tension: 0.35,
                        pointRadius: 5,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Date', color: '#9ca3af', font: { weight: 'bold', size: 13 } },
                            ticks: { color: '#9ca3af' },
                            grid: { color: 'rgba(75, 85, 99, 0.2)' }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Orders', color: '#9ca3af', font: { weight: 'bold', size: 13 } },
                            ticks: { color: '#9ca3af', precision: 0 },
                            grid: { color: 'rgba(75, 85, 99, 0.2)' }
                        }
                    }
                }
            });
        });
});
</script>
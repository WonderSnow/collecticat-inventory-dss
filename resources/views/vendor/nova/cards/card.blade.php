<div class="card p-6 mb-4" style="background-color: #111827; border-radius: 8px; border: 1px solid #1f2937;">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-white">Transaction Volume Trend</h3>
    </div>
    <div style="position: relative; height: 360px; width: 100%;">
        <canvas id="systemOrderAxisChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    fetch('/api/system-order-trend')
        .then(res => res.json())
        .then(data => {
            const ctx = document.getElementById('systemOrderAxisChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Transaction Volume',
                        data: data.sales,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { color: '#ffffff' } }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Date', color: '#9ca3af', font: { weight: 'bold' } },
                            ticks: { color: '#9ca3af' },
                            grid: { color: 'rgba(75, 85, 99, 0.3)' }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Orders', color: '#9ca3af', font: { weight: 'bold' } },
                            ticks: { color: '#9ca3af', precision: 0 },
                            grid: { color: 'rgba(75, 85, 99, 0.3)' }
                        }
                    }
                }
            });
        });
});
</script>
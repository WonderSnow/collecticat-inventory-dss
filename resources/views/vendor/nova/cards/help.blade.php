<div class="card p-6 mb-4" style="background-color: #111827; border-radius: 8px; border: 1px solid #1f2937;">
    <div class="flex items-center justify-center mb-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold text-blue-400 bg-blue-950 border border-blue-800">
            <span class="w-2 h-2 mr-2 bg-blue-400 rounded-full"></span> Transaction Volume
        </span>
    </div>
    <div style="position: relative; height: 380px; width: 100%;">
        <canvas id="siteOrderTrendCanvas"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    fetch('/api/system-order-trend')
        .then(res => res.json())
        .then(data => {
            const canvas = document.getElementById('siteOrderTrendCanvas');
            if(!canvas) return;
            const ctx = canvas.getContext('2d');

            // Create smooth gradient fill under the line
            const gradient = ctx.createLinearGradient(0, 0, 0, 350);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

            new Chart(ctx, {
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
                        tension: 0.35, // Smooth curves matching your reference
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
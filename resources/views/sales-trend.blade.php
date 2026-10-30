@extends('layouts.app')

@section('content')

<style>
.chart-toggle-btn {
    padding: 8px 14px;
    border-radius: 8px;
    font-weight: 600;
    transition: 0.3s;
}

/* DARK MODE */
html[data-theme="dark"] .chart-toggle-btn {
    background: #1f2937;
    color: #e5e7eb;
    border: 1px solid #374151;
}

/* LIGHT MODE */
html[data-theme="light"] .chart-toggle-btn {
    background: #111827;
    color: #ffffff;
    border: 1px solid #111827;
}

.chart-box {
    overflow-x: auto;
    padding: 16px;
    border-radius: 12px;
    
    display: flex;
    flex-direction: column;
    gap: 16px;

    /* ✅ smooth scrolling feel */
    scroll-behavior: smooth;
}

/* scrollbar styling */
.chart-box::-webkit-scrollbar {
    height: 8px;
}
.chart-box::-webkit-scrollbar-thumb {
    background: #475569;
    border-radius: 10px;
}
.form-control.form-select {
    height: 38px;
    font-size: 14px;
}

/* ✅ Stockout Theme Support */
.stockout-box {
    margin-top: 15px;
    padding: 12px;
    border-radius: 8px;
}

html[data-theme="dark"] .stockout-box {
    background: #020617;
    color: #e5e7eb;
    border: 1px solid #334155;
}

html[data-theme="light"] .stockout-box {
    background: #ffffff;
    color: #111827;
    border: 1px solid #d1d5db;
}
</style>

<div class="px-8 py-6">

    <h1 class="text-2xl font-bold mb-4">
        {{ $product->name }} Demand Frequency
    </h1>

    <div class="chart-box">
        <div class="mb-4 flex gap-2">
            <select id="trendType" class="form-control form-select">
                <option value="day">Daily</option>
                <option value="week">Weekly</option>
                <option value="month">Monthly</option>
                <option value="year">Yearly</option>
            </select>
        </div>
    
        <div class="stockout-box">
            <h2 class="text-lg font-bold mb-2">Product Summary</h2>
            <p id="totalSales"></p>
            <p id="totalRevenue" class="font-semibold text-green-400"></p>
            <p id="totalStocks"></p> 
            <p id="salesRate"></p>
        </div>

        <!-- Month selector ABOVE chart -->
        <select id="monthSelector" class="form-control form-select" style="display:none;"></select>

        <canvas id="chart" height="120"></canvas>

        <button id="toggleChart" class="chart-toggle-btn">
            Switch to Bar Graph
        </button>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let chart;
let chartType = 'line'; // default
let selectedMonth = null;

function isDarkMode() {
    return document.documentElement.getAttribute('data-theme') === 'dark';
}

function getTheme() {
    return isDarkMode()
        ? { text:"#e5e7eb", grid:"#334155" }
        : { text:"#111827", grid:"#e5e7eb" };
}

/* ===== PROCESSING FUNCTIONS ===== */
function processDailyData(labels, sales, revenue = []) {
    let startIndex = 0;
    for (let i = 0; i < sales.length; i++) {
        if (Number(sales[i]) > 0) {
            startIndex = i;
            break;
        }
    }

    let trimmedLabels = labels.slice(startIndex);
    let trimmedSales = sales.slice(startIndex);
    let trimmedRevenue = revenue.length ? revenue.slice(startIndex) : [];

    let newLabels = trimmedLabels.map(l => new Date(l).getDate());

    const latestDate = new Date(Math.max(...trimmedLabels.map(d => new Date(d))));
    const monthYear = `${String(latestDate.getMonth()+1).padStart(2,'0')}-${latestDate.getFullYear()}`;

    return { 
        labels: newLabels, 
        sales: trimmedSales, 
        revenue: trimmedRevenue,
        monthYear, 
        startIndex 
    };
}

function processWeeklyData(labels, sales, revenue = []) {
    let newLabels = [];
    let newSales = [];
    let newRevenue = [];
    let seen = new Set();
    labels.forEach((label, index) => {
        let finalDate = label.includes(' - ') ? label.split(' - ')[1] : label;
        if(!seen.has(finalDate)){
            seen.add(finalDate);
            newLabels.push(finalDate);
            newSales.push(sales[index]);
            if(revenue.length) newRevenue.push(revenue[index]);
        }
    });
    return { labels: newLabels, sales: newSales, revenue: newRevenue };
}

/* ===== MONTH SELECTOR ===== */
function populateMonthSelector(labels, mode = 'month') {
    const selector = document.getElementById('monthSelector');
    selector.innerHTML = '';

    if (mode === 'year') {
        const years = getAvailableYears(labels);

        years.forEach(year => {
            const opt = document.createElement('option');
            opt.value = year;
            opt.text = year;
            selector.appendChild(opt);
        });

        if (!selectedMonth && years.length) {
            selectedMonth = String(years[0]);
        }

        selector.value = selectedMonth;
        selector.style.display = 'block';
        return;
    }

    const months = new Set();
    labels.forEach(label => {
        let d;

        if (label.includes(' - ')) {
            const [startStr, endStr] = label.split(' - ');
            const start = new Date(startStr);
            const end = new Date(endStr);

            if (!isNaN(start) && !isNaN(end)) {
                months.add(`${start.getFullYear()}-${start.getMonth() + 1}`);

                if (
                    start.getMonth() !== end.getMonth() ||
                    start.getFullYear() !== end.getFullYear()
                ) {
                    months.add(`${end.getFullYear()}-${end.getMonth() + 1}`);
                }
            }
            return;
        } else {
            d = new Date(label);
        }

        if (!isNaN(d)) {
            months.add(`${d.getFullYear()}-${d.getMonth() + 1}`);
        }
    });

    const sorted = Array.from(months).sort((a, b) => {
        const [yA, mA] = a.split('-').map(Number);
        const [yB, mB] = b.split('-').map(Number);
        return yB * 12 + mB - (yA * 12 + mA);
    });

    sorted.forEach(key => {
        const [y, m] = key.split('-');
        const opt = document.createElement('option');
        opt.value = key;
        opt.text = new Date(y, m - 1).toLocaleString('default', {
            month: 'long',
            year: 'numeric'
        });
        selector.appendChild(opt);
    });

    if (!selectedMonth && sorted.length) {
        selectedMonth = sorted[0];
    }

    selector.value = selectedMonth;
    selector.style.display = 'block';
}

function getLatestMonth(labels) {
    const dates = labels
        .map(d => new Date(d))
        .filter(d => !isNaN(d));

    if (!dates.length) return null;

    const latest = new Date(Math.max(...dates));
    return `${latest.getFullYear()}-${latest.getMonth() + 1}`;
}

function getAvailableYears(labels) {
    const years = new Set();
    labels.forEach(l => {
        const d = new Date(l);
        if (!isNaN(d)) years.add(d.getFullYear());
    });
    return Array.from(years).sort((a, b) => b - a);
}

function filterByMonth(labels, sales, stocks, revenue, monthValue) {
    if (!monthValue) return { labels, sales, stocks, revenue };

    const [year, month] = monthValue.split('-').map(Number);

    let filteredLabels = [];
    let filteredSales = [];
    let filteredStocks = [];
    let filteredRevenue = [];

    labels.forEach((label, index) => {
        let d;

        if (label.includes(' - ')) {
            const [startStr, endStr] = label.split(' - ');
            const start = new Date(startStr);
            const end = new Date(endStr);

            if (isNaN(start) || isNaN(end)) return;

            const startMonthMatch =
                start.getFullYear() === year &&
                (start.getMonth() + 1) === month;

            const endMonthMatch =
                end.getFullYear() === year &&
                (end.getMonth() + 1) === month;

            if (startMonthMatch || endMonthMatch) {
                filteredLabels.push(label);
                filteredSales.push(sales[index]);
                filteredStocks.push(stocks[index] ?? null);
                if(revenue.length) filteredRevenue.push(revenue[index] ?? 0);
            }

            return;
        } else {
            d = new Date(label);
        }

        if (!isNaN(d) &&
            d.getFullYear() === year &&
            (d.getMonth() + 1) === month) {

            filteredLabels.push(label);
            filteredSales.push(sales[index]);
            filteredStocks.push(stocks[index] ?? null);
            if(revenue.length) filteredRevenue.push(revenue[index] ?? 0);
        }
    });

    return {
        labels: filteredLabels,
        sales: filteredSales,
        stocks: filteredStocks,
        revenue: filteredRevenue
    };
}

/* ===== LOAD TREND ===== */
function loadTrend(type, monthValue = null) {
    if (monthValue !== null && type === 'day') {
        selectedMonth = monthValue;
    }
    let url = `/api/product-trend/{{ $product->id }}/${type}`;
    if(monthValue) url += `?month=${monthValue}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if(chart) chart.destroy();
            const theme = getTheme();
            const ctx = document.getElementById('chart');
            const container = ctx.parentElement;
            const selector = document.getElementById('monthSelector');

            if (type === 'year') {
                selectedMonth = null;
                selector.style.display = 'none';
                selector.innerHTML = '';
            }

            let labels = data.labels;
            let sales = data.sales;
            let stocks = data.stocks || [];
            let revenue = data.revenue || [];
            let xAxisTitle = 'Date'; // Changed from 'Time' to 'Date'

            if (type === 'day') {
                if (!selectedMonth) {
                    selectedMonth = getLatestMonth(data.labels);
                }

                populateMonthSelector(data.labels, 'month');

                const filtered = filterByMonth(labels, sales, stocks, revenue, selectedMonth);

                labels = filtered.labels;
                sales = filtered.sales;
                stocks = filtered.stocks;
                revenue = filtered.revenue;

                const processed = processDailyData(labels, sales, revenue);
                
                labels = processed.labels;
                sales = processed.sales;   
                revenue = processed.revenue;
                stocks = stocks.slice(processed.startIndex);

                xAxisTitle = `Date (${processed.monthYear})`; // Changed from 'Time' to 'Date'
            }

            if (type === 'month' && type !== 'year') {
                populateMonthSelector(data.labels, 'year');

                const years = getAvailableYears(data.labels);
                if (!years.length) return;

                selectedMonth = years.includes(Number(selectedMonth))
                    ? selectedMonth
                    : String(years[0]);

                const year = Number(selectedMonth);

                let filteredLabels = [];
                let filteredSales = [];
                let filteredRevenue = [];

                labels.forEach((label, index) => {
                    const d = new Date(label);
                    if (!isNaN(d) && d.getFullYear() === year) {
                        filteredLabels.push(label);
                        filteredSales.push(sales[index]);
                        if(revenue.length) filteredRevenue.push(revenue[index]);
                    }
                });

                labels = filteredLabels;
                sales = filteredSales;
                revenue = filteredRevenue;

                xAxisTitle = `Date (${year})`; // Changed from 'Time' to 'Date'
            }

            if (type === 'week') {
                populateMonthSelector(data.labels, 'month');
                selector.style.display = 'block';

                if (!selectedMonth) {
                    selectedMonth = getLatestMonth(data.labels);
                }

                const filtered = filterByMonth(data.labels, data.sales, data.stocks || [], data.revenue || [], selectedMonth);

                labels = filtered.labels;
                sales = filtered.sales;
                stocks = filtered.stocks;
                revenue = filtered.revenue;

                const previousWeek = data.labels.find(label => {
                    if (!label.includes(' - ')) return false;

                    const [startStr, endStr] = label.split(' - ');
                    const start = new Date(startStr);
                    const end = new Date(endStr);

                    const [year, month] = selectedMonth.split('-').map(Number);

                    return (
                        !isNaN(start) &&
                        !isNaN(end) &&
                        end.getFullYear() === year &&
                        (end.getMonth() + 1) === month &&
                        (start.getMonth() + 1) !== month
                    );
                });

                if (previousWeek) {
                    const index = data.labels.indexOf(previousWeek);
                    const [, endStr] = previousWeek.split(' - ');

                    labels.unshift(endStr);          
                    sales.unshift(data.sales[index]); 
                    if(data.revenue && data.revenue[index] !== undefined) {
                        revenue.unshift(data.revenue[index]);
                    }
                }

                if (labels.length === 0) {
                    selectedMonth = getLatestMonth(data.labels);

                    const retry = filterByMonth(data.labels, data.sales, data.stocks || [], data.revenue || [], selectedMonth);
                    labels = retry.labels;
                    sales = retry.sales;
                    stocks = retry.stocks;
                    revenue = retry.revenue;
                }

                const processed = processWeeklyData(labels, sales, revenue);
                labels = processed.labels;
                sales = processed.sales;
                revenue = processed.revenue;

                const [y, m] = selectedMonth.split('-');
                const formattedMonth = new Date(y, m - 1).toLocaleString('default', {
                    month: 'long',
                    year: 'numeric'
                });

                xAxisTitle = `Date (${formattedMonth})`; // Changed from 'Time' to 'Date'
            }

            container.style.minWidth = (labels.length * 60) + 'px';

            Chart.defaults.font.family = 'Nunito';
            Chart.defaults.color = '#ffffff';
            Chart.defaults.font.size = 14;

            chart = new Chart(ctx, {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Transaction Volume',
                        data: sales,
                        borderColor: '#60a5fa',
                        backgroundColor: chartType==='bar' ? '#60a5fa':'rgba(96,165,250,0.15)',
                        fill: chartType==='line',
                        pointRadius: chartType==='line'?5:0,
                        pointHoverRadius: chartType==='line'?7:0,
                        pointBackgroundColor: '#60a5fa',
                        pointBorderColor: '#60a5fa',
                        pointBorderWidth: 2,
                        clip: false,
                        borderWidth: 3,
                        tension: chartType==='line'?0.4:0
                    }]
                },
                options: {
                    layout: {
                        padding: { bottom: 10 }
                    },
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    const index = context[0].dataIndex;
                                    let displayLabel = context[0].chart.data.labels[index];
                                    
                                    const selector = document.getElementById('monthSelector');
                                    const selectedValue = selector.value;

                                    if (document.getElementById('trendType').value === 'day' && selectedValue) {
                                        const [year, month] = selectedValue.split('-').map(Number);
                                        const d = new Date(year, month - 1, displayLabel);
                                        
                                        if (!isNaN(d)) {
                                            return `Date: ${
                                                String(d.getMonth() + 1).padStart(2, '0')}/${
                                                String(d.getDate()).padStart(2, '0')}/${
                                                d.getFullYear()
                                            }`;
                                        }
                                    }

                                    return `Period: ${displayLabel}`;
                                },
                                label: function(context) {
                                    return `Orders Confirmed: ${context.raw}`;
                                }
                            }
                        },
                        legend: {
                            labels: { color: "#ffffff", font: { size: 16, weight: 'bold' } }
                        }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: xAxisTitle, color: "#ffffff", font: { size:20, weight:'bold' } },
                            ticks: { color:"#ffffff", font:{size:14} },
                            grid: { color: theme.grid },
                            min: -0.5
                        },
                        y: {
                            beginAtZero: true,   
                            min: 0,              
                            title: {
                                display: true,
                                text: 'Orders',
                                color: "#ffffff",
                                font: { size: 20, weight: 'bold' }
                            },
                            ticks: {
                                color: "#ffffff",
                                font: { size: 14 }
                            },
                            grid: { color: theme.grid }
                        }
                    }
                }
            });

            let displayedTotalOrders = Number(sales.reduce((a, b) => a + Number(b), 0));
            let displayedTotalStocks = Number(stocks.reduce((a, b) => a + Number(b), 0));
            let totalRevenueAmount = Number(revenue.reduce((a, b) => a + Number(b), 0));

            let countOfDays = sales.length > 0 ? sales.length : 1;
            const fixedRate = Math.round(displayedTotalStocks / countOfDays);

            document.getElementById('totalSales').innerText =
                `Total Orders Completed: ${displayedTotalOrders.toLocaleString()}`;

            document.getElementById('totalRevenue').innerText =
                `Total Sales: ₱${totalRevenueAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

            document.getElementById('totalStocks').innerText =
                `Total Stocks Sold: ${displayedTotalStocks.toLocaleString()}`;

            const trendTypeDisplay = document.getElementById('trendType').value;
            document.getElementById('salesRate').innerText =
                `Stocks Sold: ${fixedRate.toLocaleString()}/${trendTypeDisplay}`;
        });
}

/* ===== EVENT LISTENERS ===== */
document.getElementById('trendType').addEventListener('change', function () {
    selectedMonth = null; 
    loadTrend(this.value);
});

document.getElementById('toggleChart').addEventListener('click', function(){
    chartType = chartType==='line'?'bar':'line';
    this.innerText = chartType==='line'?'Switch to Bar Graph':'Switch to Line Graph';
    loadTrend(document.getElementById('trendType').value, document.getElementById('monthSelector').value);
});

document.getElementById('monthSelector').addEventListener('change', function() {
    selectedMonth = this.value;
    loadTrend(document.getElementById('trendType').value, selectedMonth);
});

loadTrend('day');

new MutationObserver(() => loadTrend(document.getElementById('trendType').value, document.getElementById('monthSelector').value))
    .observe(document.documentElement, { attributes:true, attributeFilter:['data-theme'] });
</script>

@endsection
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full font-sans antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1280">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ \Laravel\Nova\Nova::name() }}</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,800,800i,900,900i" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ mix('app.css', 'vendor/nova') }}">

    <!-- Tool Styles -->
    @foreach(\Laravel\Nova\Nova::availableStyles(request()) as $name => $path)
        @if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://']))
            <link rel="stylesheet" href="{!! $path !!}">
        @else
            <link rel="stylesheet" href="/nova-api/styles/{{ $name }}">
        @endif
    @endforeach

    <!-- Custom Meta Data -->
    @include('nova::partials.meta')

    <!-- Theme Styles -->
    @foreach(\Laravel\Nova\Nova::themeStyles() as $publicPath)
        <link rel="stylesheet" href="{{ $publicPath }}">
    @endforeach
</head>
<body class="min-w-site bg-40 text-90 font-medium min-h-full">
    <div id="nova">
        <div v-cloak class="flex min-h-screen">
            <!-- Sidebar -->
            <div class="flex-none pt-header min-h-screen w-sidebar bg-grad-sidebar px-6">
                <a href="{{ \Laravel\Nova\Nova::path() }}">
                    <div class="absolute pin-t pin-l pin-r bg-logo flex items-center w-sidebar h-header px-6 text-white">
                       @include('nova::partials.logo')
                    </div>
                </a>

                @foreach (\Laravel\Nova\Nova::availableTools(request()) as $tool)
                    {!! $tool->renderNavigation() !!}
                @endforeach
            </div>

            <!-- Content -->
            <div class="content">
                <div class="flex items-center relative shadow h-header bg-white z-20 px-view">
                    <a v-if="@json(\Laravel\Nova\Nova::name() !== null)" href="{{ \Illuminate\Support\Facades\Config::get('nova.url') }}" class="no-underline dim font-bold text-90 mr-6">
                        {{ \Laravel\Nova\Nova::name() }}
                    </a>

                    @if (count(\Laravel\Nova\Nova::globallySearchableResources(request())) > 0)
                        <global-search dusk="global-search-component"></global-search>
                    @endif

                    <dropdown class="ml-auto h-9 flex items-center dropdown-right">
                        @include('nova::partials.user')
                    </dropdown>
                </div>

                <div data-testid="content" class="px-view py-view mx-auto">
                    <!-- ✅ Website Summary Heading -->
                    <div class="flex items-center mb-6" id="dashboardHeadingWrapper" style="display: none;">
                        <h1 class="text-90 font-normal text-2xl">Website Summary</h1>
                    </div>

                    <!-- ✅ Real-Time Order Trend Graph Card -->
                    <div class="card p-6 mb-6" id="trendCardContainer" style="border-radius: 8px; width: 100%; display: none;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                            <h3 id="trendCardTitle" style="font-size: 1.125rem; font-weight: bold;">Order Trend</h3>
                            <div>
                                <select id="trendRangeSelect" style="padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.875rem; outline: none; cursor: pointer;">
                                    <option value="week">This Week (Sun - Sat)</option>
                                    <option value="month" selected>This Month (Realtime)</option>
                                    <option value="today">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div style="position: relative; height: 380px; width: 100%;">
                            <canvas id="siteOrderTrendCanvas"></canvas>
                        </div>
                    </div>

                    <!-- ✅ Summary Cards Section -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6" id="summaryMetricsContainer" style="display: none; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.5rem;">
                        <!-- Card 1: Overall Revenues -->
                        <div class="card p-6" id="metricCard1" style="border-radius: 8px;">
                            <p class="text-sm font-semibold uppercase tracking-wider" id="metricLabel1" style="color: #6b7280; margin-bottom: 0.5rem;">Overall Revenues</p>
                            @php
                                $confirmedOrderIds = \App\Models\Order::where('status', \App\Models\Order::STATUS_CONFIRMED)->pluck('id');
                                $overallRevenues = \App\Models\OrderItem::whereIn('order_id', $confirmedOrderIds)
                                    ->sum(\Illuminate\Support\Facades\DB::raw('qty * price'));
                            @endphp
                            <h2 class="text-2xl font-bold" id="metricValue1" style="font-size: 1.75rem;">₱{{ number_format($overallRevenues ?? 0, 2) }}</h2>
                        </div>

                        <!-- Card 2: Highly Ordered Card -->
                        <div class="card p-6" id="metricCard2" style="border-radius: 8px;">
                            <p class="text-sm font-semibold uppercase tracking-wider" id="metricLabel2" style="color: #6b7280; margin-bottom: 0.5rem;">Highly Ordered Card</p>
                            @php
                                $topItem = \App\Models\OrderItem::select('product_id', \Illuminate\Support\Facades\DB::raw('sum(qty) as total_qty'))
                                    ->whereIn('order_id', $confirmedOrderIds)
                                    ->groupBy('product_id')
                                    ->orderByDesc('total_qty')
                                    ->with('product')
                                    ->first();
                                $topProductName = $topItem && $topItem->product ? $topItem->product->name : ($topItem->product_id ?? 'N/A');
                            @endphp
                            <h2 class="text-2xl font-bold" id="metricValue2" style="font-size: 1.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $topProductName }}">{{ $topProductName }}</h2>
                        </div>

                        <!-- Card 3: Total Revenue from Highly Ordered Card -->
                        <div class="card p-6" id="metricCard3" style="border-radius: 8px;">
                            <p class="text-sm font-semibold uppercase tracking-wider" id="metricLabel3" style="color: #6b7280; margin-bottom: 0.5rem;">Top Item Revenue</p>
                            @php
                                $topItemRevenue = 0;
                                if ($topItem) {
                                    $topItemRevenue = \App\Models\OrderItem::whereIn('order_id', $confirmedOrderIds)
                                        ->where('product_id', $topItem->product_id)
                                        ->sum(\Illuminate\Support\Facades\DB::raw('qty * price'));
                                }
                            @endphp
                            <h2 class="text-2xl font-bold" id="metricValue3" style="font-size: 1.75rem;">₱{{ number_format($topItemRevenue, 2) }}</h2>
                        </div>
                    </div>

                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        let trendChartInstance = null;

                        function isDarkMode() {
                            const bodyBg = window.getComputedStyle(document.body).backgroundColor;
                            if (document.documentElement.classList.contains('dark') || document.body.classList.contains('dark')) return true;
                            if (bodyBg) {
                                const rgb = bodyBg.match(/\d+/g);
                                if (rgb && (parseInt(rgb[0]) < 50 && parseInt(rgb[1]) < 50 && parseInt(rgb[2]) < 50)) return true;
                            }
                            return false;
                        }

                        function applyThemeStyles() {
                            const dark = isDarkMode();
                            const container = document.getElementById('trendCardContainer');
                            const title = document.getElementById('trendCardTitle');
                            const select = document.getElementById('trendRangeSelect');
                            
                            const metricsContainer = document.getElementById('summaryMetricsContainer');
                            for (let i = 1; i <= 3; i++) {
                                const card = document.getElementById('metricCard' + i);
                                const lbl = document.getElementById('metricLabel' + i);
                                const val = document.getElementById('metricValue' + i);
                                if (!card) continue;

                                if (dark) {
                                    card.style.backgroundColor = '#111827';
                                    card.style.border = '1px solid #1f2937';
                                    card.style.boxShadow = 'none';
                                    if(lbl) lbl.style.color = '#9ca3af';
                                    if(val) val.style.color = '#ffffff';
                                } else {
                                    card.style.backgroundColor = '#ffffff';
                                    card.style.border = '1px solid #e5e7eb';
                                    card.style.boxShadow = '0 1px 3px 0 rgba(0, 0, 0, 0.1)';
                                    if(lbl) lbl.style.color = '#6b7280';
                                    if(val) val.style.color = '#111827';
                                }
                            }

                            if(!container) return;

                            if (dark) {
                                container.style.backgroundColor = '#111827';
                                container.style.border = '1px solid #1f2937';
                                container.style.boxShadow = 'none';
                                title.style.color = '#ffffff';
                                select.style.backgroundColor = '#1f2937';
                                select.style.color = '#ffffff';
                                select.style.border = '1px solid #374151';
                            } else {
                                container.style.backgroundColor = '#ffffff';
                                container.style.border = '1px solid #e5e7eb';
                                container.style.boxShadow = '0 1px 3px 0 rgba(0, 0, 0, 0.1)';
                                title.style.color = '#111827';
                                select.style.backgroundColor = '#f9fafb';
                                select.style.color = '#111827';
                                select.style.border = '1px solid #d1d5db';
                            }
                        }

                        function renderChart(range) {
                            const canvas = document.getElementById('siteOrderTrendCanvas');
                            if(!canvas) return;
                            const ctx = canvas.getContext('2d');

                            applyThemeStyles();
                            fetch('/api/system-order-trend?range=' + range)
                                .then(res => res.json())
                                .then(apiData => {
                                    const dark = isDarkMode();
                                    const textColor = dark ? '#9ca3af' : '#4b5563';
                                    const gridColor = dark ? 'rgba(75, 85, 99, 0.2)' : 'rgba(209, 213, 219, 0.5)';
                                    const borderColor = '#3b82f6';
                                    
                                    const gradient = ctx.createLinearGradient(0, 0, 0, 380);
                                    if (dark) {
                                        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
                                        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');
                                    } else {
                                        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.25)');
                                        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');
                                    }

                                    if (trendChartInstance) {
                                        trendChartInstance.destroy();
                                    }

                                    let chartConfig = {
                                        type: 'line',
                                        data: {
                                            labels: apiData.labels || [],
                                            datasets: [{
                                                label: range === 'today' ? 'Orders' : 'Order Count',
                                                data: apiData.sales || [],
                                                borderColor: borderColor,
                                                backgroundColor: gradient,
                                                fill: true,
                                                borderWidth: 3,
                                                tension: 0.35,
                                                pointRadius: 5,
                                                pointBackgroundColor: borderColor,
                                                pointBorderColor: dark ? '#111827' : '#ffffff',
                                                pointBorderWidth: 2
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: { legend: { display: false } },
                                            scales: {
                                                x: {
                                                    title: { display: true, text: range === 'today' ? 'Years (From First Purchase Onwards)' : (range === 'week' ? 'Days of Week (Sun - Sat)' : 'Current Month Days'), color: textColor, font: { weight: 'bold', size: 13 } },
                                                    ticks: { color: textColor },
                                                    grid: { color: gridColor }
                                                },
                                                y: {
                                                    beginAtZero: true,
                                                    title: { display: true, text: 'Orders', color: textColor, font: { weight: 'bold', size: 13 } },
                                                    ticks: { color: textColor, precision: 0 },
                                                    grid: { color: gridColor }
                                                }
                                            }
                                        }
                                    };

                                    trendChartInstance = new Chart(ctx, chartConfig);
                                });
                        }

                        let lastPath = window.location.pathname;
                        function checkDashboardRoute() {
                            const container = document.getElementById('trendCardContainer');
                            const metrics = document.getElementById('summaryMetricsContainer');
                            const heading = document.getElementById('dashboardHeadingWrapper');
                            if (!container || !heading || !metrics) return;

                            const path = window.location.pathname;
                            const isDashboard = path === '/admin' || path === '/admin/' || path.includes('/admin/dashboards/main');

                            if (isDashboard) {
                                container.style.display = 'block';
                                metrics.style.display = 'grid';
                                heading.style.display = 'flex';
                                renderChart(document.getElementById('trendRangeSelect').value);
                            } else {
                                container.style.display = 'none';
                                metrics.style.display = 'none';
                                heading.style.display = 'none';
                            }
                        }

                        setInterval(() => {
                            if (window.location.pathname !== lastPath) {
                                lastPath = window.location.pathname;
                                checkDashboardRoute();
                            }
                        }, 100);

                        setTimeout(checkDashboardRoute, 200);

                        const rangeSelect = document.getElementById('trendRangeSelect');
                        if(rangeSelect) {
                            rangeSelect.addEventListener('change', function(e) {
                                renderChart(e.target.value);
                            });
                        }

                        const observer = new MutationObserver(() => applyThemeStyles());
                        observer.observe(document.body, { attributes: true, attributeFilter: ['class', 'style'] });
                    });
                    </script>

                    @yield('content')

                    @include('nova::partials.footer')
                </div>
            </div>
        </div>
    </div>

    <script>
        window.config = @json(\Laravel\Nova\Nova::jsonVariables(request()));
    </script>

    <!-- Scripts -->
    <script src="{{ mix('manifest.js', 'vendor/nova') }}"></script>
    <script src="{{ mix('vendor.js', 'vendor/nova') }}"></script>
    <script src="{{ mix('app.js', 'vendor/nova') }}"></script>

    <!-- Build Nova Instance -->
    <script>
        window.Nova = new CreateNova(config)
    </script>

    <!-- Tool Scripts -->
    @foreach (\Laravel\Nova\Nova::availableScripts(request()) as $name => $path)
        @if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://']))
            <script src="{!! $path !!}"></script>
        @else
            <script src="/nova-api/scripts/{{ $name }}"></script>
        @endif
    @endforeach

    <!-- Start Nova -->
    <script>
        Nova.liftOff()
    </script>

    <!-- ✅ BULLETPROOF TARGETING: Specifically find the play button next to the Select Action dropdown -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const observer = new MutationObserver(() => {
                // Find all <select> elements that represent action selectors
                const selects = document.querySelectorAll('select');
                selects.forEach(select => {
                    // Check if this select is the action dropdown (usually has options or is inside an action toolbar)
                    if (select.closest('div')) {
                        const toolbar = select.closest('div');
                        const btn = toolbar.querySelector('button:not(.proceed-replaced)');
                        
                        // Verify the button has an SVG and is right next to the action select
                        if (btn && btn.querySelector('svg') && !btn.classList.contains('proceed-replaced')) {
                            btn.innerHTML = '<span style="font-weight: 600; padding: 2px 8px; color: #ffffff;">Proceed</span>';
                            btn.style.width = 'auto';
                            btn.style.padding = '4px 12px';
                            btn.classList.add('proceed-replaced');
                        }
                    }
                });
            });

            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
</body>
</html>
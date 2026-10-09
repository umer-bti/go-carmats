<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', sans-serif;
        }

        /* CENTERED LAYOUT */
        .page-wrapper {
            max-width: 1400px;
            margin: auto;
            padding: 40px 20px;
        }

        .card-custom {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: 0.25s;
            background: #fff;
        }

        .card-custom:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        /* BIGGER STATS */
        .stat-value {
            font-size: 32px;
            font-weight: 600;
        }

        .stat-label {
            font-size: 14px;
            color: #6c757d;
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .bg-soft-success {
            background: #e6f7ee;
            color: #28a745;
        }

        .bg-soft-primary {
            background: #e7f1ff;
            color: #0d6efd;
        }

        /* HEADER */
        .header-title {
            font-size: 22px;
            font-weight: 600;
        }

        .header-sub {
            font-size: 13px;
            color: #6c757d;
        }

        /* STITCHER LIST */
        .stitcher-item {
            padding: 14px 18px;
            border-radius: 12px;
            background: #f8f9fb;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.2s;
        }

        .stitcher-item:hover {
            background: #eef2f7;
            transform: scale(1.01);
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #d9d9ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #5a5ad1;
        }

        .section-title {
            font-weight: 600;
            font-size: 18px;
        }

        .total-badge {
            background: #eef2f7;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
        }
    </style>
</head>

<body>

    <div class="page-wrapper">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <div class="header-title">Overview</div>
                <div class="header-sub">{{ \Carbon\Carbon::today()->format('l, j F Y') }}</div>
            </div>
            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill">
                Live Stats
            </span>
        </div>

        <!-- STATS CARDS -->
        <div class="row g-4 mb-4">

            <div class="col-md-6">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Total Batches</div>
                            <div class="stat-value" id="totalBatches">
                                {{ number_format($batchesCreatedToday) }}
                            </div>
                        </div>
                        <div class="stat-icon bg-soft-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Total Scanned Orders</div>
                            <div class="stat-value" id="totalScans">
                                {{ number_format($scannedToday) }}
                            </div>
                        </div>
                        <div class="stat-icon bg-soft-primary">
                            <i class="bi bi-upc-scan"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">Completed Batches</div>
                            <div class="stat-value" id="completedBatches">
                                {{ number_format($batchesCompleted) }}
                            </div>
                        </div>
                        <div class="stat-icon bg-soft-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- STITCHERS SECTION -->
        <div class="card card-custom p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="section-title">Scans by Stitcher</div>
                <div class="total-badge">
                    Total: <span id="stitcherTotal">{{ number_format($scannedToday) }}</span>
                </div>
            </div>

            <div id="stitcherList">
                @foreach ($stitchersScanToday as $stitcher)
                    <div class="stitcher-item" data-stitcher-id="{{ $stitcher->id }}">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar">
                                {{ strtoupper($stitcher->name[0] ?? '') }}
                            </div>
                            <div>{{ $stitcher->name }}</div>
                        </div>
                        <strong>{{ $stitcher->orders_count }}</strong>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    <script>
        // Function to update dashboard smoothly
        function updateDashboard(data) {
            // Update Total Batches
            document.getElementById('totalBatches').textContent =
                Number(data.totalBatches).toLocaleString('en-US');

            // Update Total Scanned Orders
            document.getElementById('totalScans').textContent =
                Number(data.totalScans).toLocaleString('en-US');

            document.getElementById('completedBatches').textContent = 
                Number(data.completedBatches).toLocaleString('en-US');    

            // Update Stitchers List
            const container = document.getElementById('stitcherList');
            let total = 0;
            let html = '';

            data.stitchers.forEach(stitcher => {
                total += parseInt(stitcher.count);

                html += `
                <div class="stitcher-item" data-stitcher-id="${stitcher.id}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar">
                            ${stitcher.name.charAt(0).toUpperCase()}
                        </div>
                        <div>${stitcher.name}</div>
                    </div>
                    <strong>${stitcher.count}</strong>
                </div>
            `;
            });

            // Update only if content changed (prevents unnecessary flicker)
            if (container.innerHTML.trim() !== html.trim()) {
                container.innerHTML = html;
            }

            // Update total scans by stitchers
            document.getElementById('stitcherTotal').textContent = total.toLocaleString('en-US');
        }

        // Fetch latest data via AJAX
        async function fetchLiveStats() {
            try {
                const response = await fetch("{{ route('daily-stats') }}", {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();
                updateDashboard(data);

            } catch (error) {
                console.error('Error fetching live stats:', error);
            }
        }

        // Initialize auto-refresh
        document.addEventListener('DOMContentLoaded', function() {
            // Auto refresh every 20 seconds
            setInterval(fetchLiveStats, 20000);
        });
    </script>

</body>

</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Overview</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --page-max-width: 100%;
            --page-padding-y: 32px;
            --page-padding-x: 32px;
            --card-radius: 24px;
            --card-padding: 32px;
            --shadow-soft: 0 12px 35px rgba(15, 23, 42, 0.08);
            --shadow-hover: 0 18px 40px rgba(15, 23, 42, 0.12);
        }

        body {
            background: linear-gradient(180deg, #f4f7fb 0%, #eef3f9 100%);
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: #172033;
        }

        .page-wrapper {
            max-width: var(--page-max-width);
            margin: auto;
            padding: var(--page-padding-y) var(--page-padding-x);
            min-height: 100vh;
        }

        .card-custom {
            border: none;
            border-radius: var(--card-radius);
            box-shadow: var(--shadow-soft);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            background: linear-gradient(180deg, #ffffff 0%, #fdfefe 100%);
        }

        .card-custom:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
        }

        .stat-value {
            font-size: clamp(2.8rem, 3.6vw, 4.8rem);
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -0.03em;
        }

        .stat-label {
            font-size: clamp(1rem, 1.1vw, 1.35rem);
            color: #5c6880;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .stat-icon {
            width: clamp(72px, 5vw, 100px);
            height: clamp(72px, 5vw, 100px);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.8rem, 2vw, 2.5rem);
            flex-shrink: 0;
        }

        .bg-soft-success { background: #e6f7ee; color: #28a745; }
        .bg-soft-primary { background: #e7f1ff; color: #0d6efd; }

        .header-title {
            font-size: clamp(2.2rem, 2.8vw, 3.6rem);
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .header-sub {
            font-size: clamp(1rem, 1.1vw, 1.35rem);
            color: #5c6880;
            margin-top: 6px;
        }

        .section-title {
            font-weight: 700;
            font-size: clamp(1.5rem, 1.8vw, 2.2rem);
            letter-spacing: -0.02em;
        }

        .stitcher-item {
            padding: 22px 28px;
            border-radius: 20px;
            background: linear-gradient(180deg, #f8fbff 0%, #f3f7fc 100%);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(13, 110, 253, 0.08);
        }

        .stitcher-item:hover {
            background: #eef5ff;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(13, 110, 253, 0.08);
        }

        .avatar {
            width: clamp(54px, 3.6vw, 72px);
            height: clamp(54px, 3.6vw, 72px);
            border-radius: 50%;
            background: #d9d9ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #5a5ad1;
            font-size: clamp(1.2rem, 1.2vw, 1.6rem);
        }

        .total-badge {
            background: #eef4ff;
            padding: 12px 24px;
            border-radius: 999px;
            font-size: clamp(1rem, 1vw, 1.2rem);
            font-weight: 600;
            color: #22407a;
        }

        .stat-card .card-custom {
            min-height: 220px;
            padding: var(--card-padding) !important;
        }

        .stats-row {
            margin-bottom: 2rem;
        }

        .stitchers-card {
            min-height: calc(100vh - 320px);
        }

        .live-badge {
            padding: 14px 26px;
            font-size: clamp(1rem, 1vw, 1.15rem);
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .stitcher-name {
            font-size: clamp(1.2rem, 1.25vw, 1.7rem);
            font-weight: 600;
        }

        .stitcher-count {
            font-size: clamp(1.4rem, 1.6vw, 2rem);
            color: #162033;
        }

        @media (min-width: 1920px) {
            .page-wrapper {
                padding: 48px 56px;
            }

            .stat-card .card-custom {
                min-height: 280px;
            }
        }

        @media (min-width: 2560px) {
            .page-wrapper {
                padding: 52px 64px;
            }
        }

        @media (min-width: 3300px) {
            :root {
                --page-max-width: 100%;
                --page-padding-y: 56px;
                --page-padding-x: 72px;
                --card-radius: 32px;
                --card-padding: 52px;
            }

            .page-wrapper {
                padding: var(--page-padding-y) var(--page-padding-x);
            }

            .header-title {
                font-size: 5rem;
            }

            .header-sub {
                font-size: 1.9rem;
                margin-top: 12px;
            }

            .live-badge {
                padding: 20px 36px;
                font-size: 1.5rem;
            }

            .stat-card .card-custom {
                min-height: 360px;
            }

            .stat-label {
                font-size: 1.7rem;
                margin-bottom: 16px;
            }

            .stat-value {
                font-size: 7rem;
            }

            .stat-icon {
                width: 132px;
                height: 132px;
                font-size: 3.6rem;
                border-radius: 30px;
            }

            .section-title {
                font-size: 3rem;
            }

            .total-badge {
                padding: 18px 34px;
                font-size: 1.55rem;
            }

            .stitcher-item {
                padding: 34px 40px;
                border-radius: 26px;
                margin-bottom: 22px;
            }

            .avatar {
                width: 92px;
                height: 92px;
                font-size: 2.2rem;
            }

            .stitcher-name {
                font-size: 2.3rem;
            }

            .stitcher-count {
                font-size: 2.8rem;
            }

            .stats-row {
                margin-bottom: 2.5rem;
            }

            .stitchers-card {
                min-height: calc(100vh - 420px);
            }
        }

        @media (min-width: 6200px) {
            :root {
                --page-max-width: 100%;
                --page-padding-y: 88px;
                --page-padding-x: 120px;
                --card-radius: 42px;
                --card-padding: 72px;
            }

            .page-wrapper {
                padding: var(--page-padding-y) var(--page-padding-x);
            }

            .header-title {
                font-size: 7rem;
            }

            .header-sub {
                font-size: 2.6rem;
                margin-top: 18px;
            }

            .live-badge {
                padding: 26px 44px;
                font-size: 2rem;
            }

            .stats-row {
                margin-bottom: 3.25rem;
            }

            .stat-card .card-custom {
                min-height: 500px;
            }

            .stat-label {
                font-size: 2.35rem;
                margin-bottom: 22px;
            }

            .stat-value {
                font-size: 9.25rem;
            }

            .stat-icon {
                width: 176px;
                height: 176px;
                font-size: 4.8rem;
                border-radius: 38px;
            }

            .section-title {
                font-size: 4rem;
            }

            .total-badge {
                padding: 24px 42px;
                font-size: 2rem;
            }

            .stitchers-card {
                min-height: calc(100vh - 580px);
            }

            .stitcher-item {
                padding: 46px 54px;
                border-radius: 32px;
                margin-bottom: 28px;
            }

            .avatar {
                width: 122px;
                height: 122px;
                font-size: 3rem;
            }

            .stitcher-name {
                font-size: 3rem;
            }

            .stitcher-count {
                font-size: 3.6rem;
            }
        }

        @media (min-width: 1400px) {
            .stat-card {
                flex: 1 1 33.333%;
            }
        }

        @media (max-width: 991.98px) {
            .page-wrapper {
                padding: 28px 18px;
            }

            .stat-card .card-custom {
                min-height: auto;
            }

            .stitcher-item {
                padding: 18px 20px;
            }
        }

        @media (max-width: 575.98px) {
            .stitcher-item {
                align-items: flex-start;
                gap: 12px;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

    <div class="page-wrapper">

        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <div class="header-title">Overview</div>
                <div class="header-sub">{{ \Carbon\Carbon::today()->format('l, j F Y') }}</div>
            </div>

            <span class="badge bg-primary-subtle text-primary rounded-pill live-badge">
                Live Stats
            </span>
        </div>

        <!-- STATS CARDS - Now 3 in one row on large screens -->
        <div class="row g-4 stats-row">
            <div class="col-lg-4 col-md-6 stat-card">
                <div class="card card-custom h-100">
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

            <div class="col-lg-4 col-md-6 stat-card">
                <div class="card card-custom h-100">
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

            <div class="col-lg-4 col-md-6 stat-card">
                <div class="card card-custom h-100">
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
        <div class="card card-custom p-4 p-xl-5 stitchers-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
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
                            <div class="stitcher-name">{{ $stitcher->name }}</div>
                        </div>
                        <strong class="stitcher-count">{{ $stitcher->orders_count }}</strong>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    <script>
        function updateDashboard(data) {
            document.getElementById('totalBatches').textContent = Number(data.totalBatches).toLocaleString('en-US');
            document.getElementById('totalScans').textContent = Number(data.totalScans).toLocaleString('en-US');
            document.getElementById('completedBatches').textContent = Number(data.completedBatches).toLocaleString('en-US');

            const container = document.getElementById('stitcherList');
            let total = 0;
            let html = '';

            data.stitchers.forEach(stitcher => {
                total += parseInt(stitcher.count || 0);
                html += `
                    <div class="stitcher-item" data-stitcher-id="${stitcher.id}">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar">${stitcher.name.charAt(0).toUpperCase()}</div>
                            <div class="stitcher-name">${stitcher.name}</div>
                        </div>
                        <strong class="stitcher-count">${stitcher.count}</strong>
                    </div>
                `;
            });

            container.innerHTML = html;
            document.getElementById('stitcherTotal').textContent = total.toLocaleString('en-US');
        }

        async function fetchLiveStats() {
            try {
                const response = await fetch("{{ route('daily-stats') }}", {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });

                if (response.ok) {
                    const data = await response.json();
                    updateDashboard(data);
                }
            } catch (error) {
                console.error('Error fetching live stats:', error);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setInterval(fetchLiveStats, 20000); // Refresh every 20 seconds
        });
        
    </script>

</body>
</html>




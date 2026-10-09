@extends('console.layout.app')

@section('title', 'Live Stats')

@push('styles')
    <style>
        .dashboard-stat-card {
            border: 1px solid rgba(67, 89, 113, 0.16);
            border-radius: 0.75rem;
            box-shadow: 0 0.125rem 0.375rem rgba(67, 89, 113, 0.06);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            overflow: hidden;
        }

        .dashboard-stat-card:hover {
            transform: translateY(-3px);
            border-color: rgba(67, 89, 113, 0.22);
            box-shadow: 0 0.5rem 1.25rem rgba(67, 89, 113, 0.12) !important;
        }

        .dashboard-stat-card .stat-value {
            font-size: 1.65rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .dashboard-overview-card {
            border-radius: 0.75rem;
            border: 1px solid rgba(67, 89, 113, 0.14);
            box-shadow: 0 0.125rem 0.5rem rgba(67, 89, 113, 0.06);
        }

        .dashboard-stitcher-panel {
            border-radius: 0.75rem;
            border: 1px solid rgba(67, 89, 113, 0.12);
            background: linear-gradient(165deg, rgba(105, 108, 255, 0.07) 0%, rgba(255, 255, 255, 0) 55%);
            box-shadow: 0 0.125rem 0.5rem rgba(67, 89, 113, 0.05);
            overflow: hidden;
        }

        .dashboard-stitcher-panel .dashboard-stitcher-panel-icon {
            width: 2.75rem;
            height: 2.75rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.65rem;
        }

        .dashboard-stitcher-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding: 0.875rem 1rem;
            border-radius: 0.65rem;
            border: 1px solid rgba(67, 89, 113, 0.08);
            background: rgba(255, 255, 255, 0.65);
        }

        [data-bs-theme="dark"] .dashboard-stitcher-row {
            background: rgba(67, 89, 113, 0.15);
        }

        .dashboard-stitcher-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .dashboard-stitcher-count {
            font-size: 1.35rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            min-width: 2.5rem;
            text-align: right;
        }

        .dashboard-stitcher-count.is-zero {
            opacity: 0.45;
        }

        .dashboard-stitcher-empty {
            border: 1px dashed rgba(67, 89, 113, 0.2);
            border-radius: 0.65rem;
            padding: 2rem 1.25rem;
            text-align: center;
        }

        .live-stats-pulse {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: #28c76f;
            animation: liveStatsPulse 1.5s ease-in-out infinite;
        }

        @keyframes liveStatsPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.35; }
        }
    </style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card dashboard-overview-card shadow-none mb-4">
            <div class="card-body py-4 px-4 px-lg-5">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-3 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-3 bg-label-success d-flex align-items-center justify-content-center"
                             style="width: 3rem; height: 3rem;" aria-hidden="true">
                            <i class="icon-base ti tabler-activity icon-28px text-success"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h4 class="mb-0">Live Stats</h4>
                                <span class="live-stats-pulse" title="Auto-refreshing"></span>
                            </div>
                            <p class="text-muted mb-0 small">Today — {{ now()->format('l, j F Y') }} · updates every 20 seconds</p>
                        </div>
                    </div>
                </div>

                <div class="row g-3 g-md-4">
                    <div class="col-lg-4 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <p class="small text-muted mb-1">Batches created</p>
                                <p class="stat-value text-heading mb-0" id="liveTotalBatches">
                                    {{ number_format($batchesCreatedToday) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <p class="small text-muted mb-1">Scanned</p>
                                <p class="stat-value text-heading mb-0" id="liveTotalScans">
                                    {{ number_format($scannedToday) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <p class="small text-muted mb-1">Batches completed</p>
                                <p class="stat-value text-heading mb-0" id="liveCompletedBatches">
                                    {{ number_format($batchesCompleted) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-stitcher-panel mt-4 pt-1">
                    <div class="p-4 pb-0">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-sm-between gap-3 mb-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="dashboard-stitcher-panel-icon bg-label-primary text-primary" aria-hidden="true">
                                    <i class="icon-base ti tabler-users icon-md"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 text-heading">Scans by stitcher</h5>
                                </div>
                            </div>
                            <div class="ms-sm-auto">
                                <span class="badge bg-label-secondary rounded-pill px-3 py-2 fw-normal">
                                    Total <span class="fw-semibold text-heading ms-1" id="liveStitcherTotal">{{ number_format($stitcherScansTotal) }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 pb-4" id="liveStitcherList">
                        @include('console.live-stats.partials.stitcher-list', ['stitchersScanToday' => $stitchersScanToday])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const liveList = document.getElementById('liveStitcherList');
            const liveTotalEl = document.getElementById('liveStitcherTotal');
            let liveTimer = null;

            function buildInitials(name) {
                const cleanName = (name || '').trim();
                if (!cleanName) return '--';
                const parts = cleanName.split(/\s+/);
                if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
                return cleanName.slice(0, 2).toUpperCase();
            }

            function renderLiveStitchers(stitchers) {
                if (!Array.isArray(stitchers) || stitchers.length === 0) {
                    liveList.innerHTML = '<div class="dashboard-stitcher-empty"><p class="text-muted mb-0">No stitchers found.</p></div>';
                    liveTotalEl.textContent = '0';
                    return;
                }

                let total = 0;
                liveList.innerHTML = stitchers.map(function(stitcher) {
                    const count = Number(stitcher.count || 0);
                    total += count;
                    const initials = buildInitials(stitcher.name || '');
                    return `
                        <div class="dashboard-stitcher-row mb-2">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <span class="dashboard-stitcher-avatar bg-label-primary text-primary">${initials}</span>
                                <div class="min-w-0">
                                    <p class="mb-0 text-heading fw-medium text-truncate">${stitcher.name || '-'}</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto">
                                <span class="dashboard-stitcher-count text-heading ${count === 0 ? 'is-zero' : ''}">${count.toLocaleString('en-US')}</span>
                            </div>
                        </div>
                    `;
                }).join('');

                liveTotalEl.textContent = total.toLocaleString('en-US');
            }
            

            function updateLiveCards(data) {
                document.getElementById('liveTotalBatches').textContent = Number(data.totalBatches || 0).toLocaleString('en-US');
                document.getElementById('liveTotalScans').textContent = Number(data.totalScans || 0).toLocaleString('en-US');
                document.getElementById('liveCompletedBatches').textContent = Number(data.completedBatches || 0).toLocaleString('en-US');
                renderLiveStitchers(data.stitchers || []);
            }

            async function fetchLiveStats() {
                try {
                    const response = await fetch("{{ route('console.liveStats.data') }}", {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) return;
                    updateLiveCards(await response.json());
                } catch (error) {
                    console.error('Failed to fetch live stats:', error);
                }
            }

            fetchLiveStats();
            liveTimer = setInterval(fetchLiveStats, 20000);

            window.addEventListener('beforeunload', function() {
                if (liveTimer) clearInterval(liveTimer);
            });
        });
    </script>
@endpush

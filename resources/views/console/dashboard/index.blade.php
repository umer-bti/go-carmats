@extends('console.layout.app')

@section('title', 'Dashboard')

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

        .dashboard-stat-card .stat-icon {
            width: 2.75rem;
            height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.65rem;
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

        /* Header calendar: stat-icon rules only apply inside .dashboard-stat-card; center icon in the circle here */
        .dashboard-overview-header-icon {
            width: 3rem;
            height: 3rem;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .dashboard-overview-header-icon>i {
            line-height: 1;
            vertical-align: middle;
        }

        /* Title left, date filter + buttons right (reliable space-between; avoids utility conflicts) */
        .dashboard-overview-toolbar {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
            width: 100%;
        }

        @media (min-width: 768px) {
            .dashboard-overview-toolbar {
                flex-direction: row;
                flex-wrap: nowrap;
                align-items: flex-start;
                justify-content: space-between;
            }
        }

        .dashboard-overview-toolbar__title {
            flex: 0 0 auto;
        }

        .dashboard-overview-toolbar__form {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.5rem;
            width: 100%;
        }

        .dashboard-overview-toolbar__filter-stack {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            width: 100%;
            min-width: 14rem;
            max-width: 18rem;
        }

        .dashboard-overview-toolbar__date-input {
            width: 100%;
        }

        .dashboard-overview-toolbar__filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        @media (min-width: 768px) {
            .dashboard-overview-toolbar__form {
                width: auto;
                flex: 0 0 auto;
                align-items: flex-end;
            }
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
            transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        }

        [data-bs-theme="dark"] .dashboard-stitcher-row {
            background: rgba(67, 89, 113, 0.15);
        }

        .dashboard-stitcher-row:hover {
            border-color: rgba(67, 89, 113, 0.16);
            box-shadow: 0 0.25rem 0.75rem rgba(67, 89, 113, 0.07);
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
            letter-spacing: 0.02em;
            flex-shrink: 0;
        }

        .dashboard-stitcher-count {
            font-size: 1.35rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
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
    </style>
@endpush

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card dashboard-overview-card shadow-none mb-4">
            <div class="card-body py-4 px-4 px-lg-5">
                <div class="dashboard-overview-toolbar mb-4">
                    <div class="dashboard-overview-toolbar__title d-flex align-items-start gap-3">
                        <div class="dashboard-overview-header-icon bg-label-primary rounded-3" aria-hidden="true">
                            <i class="icon-base ti tabler-calendar-stats icon-28px text-primary"></i>
                        </div>
                        <div>
                            <h4 class="mb-1">Overview</h4>
                            <p class="text-muted mb-0 small">{{ $selectedDate->format('l, j F Y') }}</p>
                        </div>
                    </div>
                    <form method="GET" action="{{ route('console.dashboard') }}" class="dashboard-overview-toolbar__form">
                        <div class="dashboard-overview-toolbar__filter-stack">
                            <input type="date" class="form-control dashboard-overview-toolbar__date-input" name="date"
                                value="{{ $selectedDate->format('Y-m-d') }}" required
                                aria-label="Select date">
                            <div class="dashboard-overview-toolbar__filter-actions">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-filter me-1"></i> Apply
                                </button>
                                <a href="{{ route('console.dashboard') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- <p class="text-uppercase text-muted small fw-semibold mb-3" style="letter-spacing: 0.06em;">Today</p> --}}
                <div class="row g-3 g-md-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="stat-icon bg-label-primary text-primary"><i
                                            class="icon-base ti tabler-shopping-cart icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">Orders</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($ordersToday) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="stat-icon bg-label-warning text-warning"><i
                                            class="icon-base ti tabler-brand-amazon icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">Amazon orders</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($amazonOrdersToday) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="stat-icon bg-label-info text-info"><i
                                            class="icon-base ti tabler-shopping-bag icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">eBay orders</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($ebayOrdersToday) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="stat-icon bg-label-info text-info"><i
                                            class="icon-base ti tabler-texture icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">Carpet orders</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($carpetOrdersToday) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="stat-icon bg-label-warning text-warning"><i
                                            class="icon-base ti tabler-layers-linked icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">Rubber orders</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($rubberOrdersToday) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="stat-icon bg-label-secondary text-secondary"><i
                                            class="icon-base ti tabler-scan icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">Scanned</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($scannedToday) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="stat-icon bg-label-primary text-primary"><i
                                            class="icon-base ti tabler-square-plus icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">Batches created</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($batchesCreatedToday) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="stat-icon bg-label-warning text-warning"><i
                                            class="icon-base ti tabler-stack-2 icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">Batches pending</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($batchesPending) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="stat-icon bg-label-success text-success"><i
                                            class="icon-base ti tabler-circle-check icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">Batches completed</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($batchesCompleted) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card dashboard-stat-card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="stat-icon bg-label-danger text-danger"><i
                                            class="icon-base ti tabler-arrow-back icon-md"></i></span>
                                    {{-- <span class="badge rounded-pill bg-label-secondary">Today</span> --}}
                                </div>
                                <p class="small text-muted mb-1">Returns recorded</p>
                                <p class="stat-value text-heading mb-0">{{ number_format($returnsToday) }}</p>
                            </div>
                        </div>
                    </div>
                    </div>

                    @php
                        $stitcherScansTotal = $stitchersScanToday->sum('orders_count');
                    @endphp
                    <div class="dashboard-stitcher-panel mt-4 pt-1">
                    <div class="p-4 pb-0">
                        <div
                            class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-sm-between gap-3 mb-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="dashboard-stitcher-panel-icon bg-label-primary text-primary"
                                    aria-hidden="true">
                                    <i class="icon-base ti tabler-users icon-md"></i>
                                </div>
                                <div>
                                    {{-- <h5 class="mb-0 text-heading">Today's scans by stitcher</h5> --}}
                                    <h5 class="mb-0 text-heading">Scans by stitcher</h5>
                                </div>
                            </div>
                            @if ($stitchersScanToday->isNotEmpty())
                                <div class="ms-sm-auto">
                                    <span class="badge bg-label-secondary rounded-pill px-3 py-2 fw-normal">
                                        Total <span
                                            class="fw-semibold text-heading ms-1">{{ number_format($stitcherScansTotal) }}</span>
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="px-4 pb-4">
                        @if ($stitchersScanToday->isEmpty())
                            <div class="dashboard-stitcher-empty">
                                <span
                                    class="dashboard-stitcher-panel-icon bg-label-secondary text-secondary d-inline-flex mb-3"
                                    aria-hidden="true">
                                    <i class="icon-base ti tabler-user-plus icon-md"></i>
                                </span>
                                <p class="text-heading fw-medium mb-1">No stitchers yet</p>
                                <p class="text-muted small mb-0">Add stitchers to track scans per person.</p>
                            </div>
                        @else
                            <div class="d-flex flex-column gap-2">
                                @foreach ($stitchersScanToday as $stitcher)
                                    @php
                                        $name = trim($stitcher->name);
                                        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
                                        if (count($parts) >= 2) {
                                            $initials = mb_strtoupper(
                                                mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1),
                                            );
                                        } else {
                                            $initials = mb_strtoupper(mb_substr($name, 0, min(2, mb_strlen($name))));
                                        }
                                        $n = (int) $stitcher->orders_count;
                                    @endphp
                                    <div class="dashboard-stitcher-row">
                                        <div class="d-flex align-items-center gap-3 min-w-0">
                                            <span class="dashboard-stitcher-avatar bg-label-primary text-primary"
                                                title="{{ $stitcher->name }}">{{ $initials }}</span>
                                            <div class="min-w-0">
                                                <p class="mb-0 text-heading fw-medium text-truncate">{{ $stitcher->name }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 ms-auto">
                                            <span
                                                class="dashboard-stitcher-count text-heading {{ $n === 0 ? 'is-zero' : '' }}">{{ number_format($n) }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    </div>
            </div>
        </div>
    </div>
@endsection

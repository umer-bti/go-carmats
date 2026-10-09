@if ($stitchersScanToday->isEmpty())
    <div class="dashboard-stitcher-empty">
        <p class="text-muted mb-0">No stitchers found.</p>
    </div>
@else
    <div class="d-flex flex-column gap-2">
        @foreach ($stitchersScanToday as $stitcher)
            @php
                $name = trim($stitcher->name);
                $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
                if (count($parts) >= 2) {
                    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
                } else {
                    $initials = mb_strtoupper(mb_substr($name, 0, min(2, mb_strlen($name))));
                }
                $n = (int) $stitcher->orders_count;
            @endphp
            <div class="dashboard-stitcher-row">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <span class="dashboard-stitcher-avatar bg-label-primary text-primary">{{ $initials }}</span>
                    <div class="min-w-0">
                        <p class="mb-0 text-heading fw-medium text-truncate">{{ $stitcher->name }}</p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="dashboard-stitcher-count text-heading {{ $n === 0 ? 'is-zero' : '' }}">{{ number_format($n) }}</span>
                </div>
            </div>
        @endforeach
    </div>
@endif

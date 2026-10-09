@php
    $reasonMap = $reasonMap ?? [];
@endphp

@foreach ($orders as $index => $order)
    @php
        $reason = $reasonMap[$order->id] ?? null;
    @endphp
    <div class="label-card-container {{ $index === 0 ? 'active' : 'd-none' }}"
         data-index="{{ $index }}"
         data-order-id="{{ $order->order_id }}"
         data-listing-id="{{ $order->id }}">
        <div class="card label-card">
            <div class="card-body">
                @include('console.batch-management.completed.partials.production-label', [
                    'order'          => $order,
                    'productSetting' => $order->product_setting,
                    'replacementReason' => $reason,
                ])
                <div class="label-navigation">
                    <span class="fs-5">Label {{ $index + 1 }} of {{ $orders->count() }}</span>
                </div>
            </div>
        </div>
    </div>
@endforeach

<style>
    /* Shown in the modal preview */
    .replacement-reason-banner {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff3cd;
        border: 2px solid #ffc107;
        border-radius: 6px;
        padding: 8px 14px;
        margin-bottom: 10px;
        width: 4in;
        max-width: 100%;
        margin-left: auto;
        margin-right: auto;
        box-sizing: border-box;
    }
    .replacement-reason-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: #856404;
        white-space: nowrap;
    }
    .replacement-reason-text {
        font-size: 13px;
        font-weight: 700;
        color: #333;
        word-break: break-word;
    }

    /* In the printable label itself – the pl-reason-row */
    .pl-reason-row {
        background: #111;
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 4px 10px;
        text-align: center;
        word-break: break-word;
        line-height: 1.3;
        flex-shrink: 0;
    }
</style>

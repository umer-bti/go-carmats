@php
    $productSetting = $productSetting ?? $order->product_setting ?? null;
    $replacementReason = $replacementReason ?? null;
    $vehicleTitle = strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($order->make_model ?? '')))) ?: '-';

    $mats = $productSetting?->no_of_mats ?? '-';
    $clips = $productSetting?->no_of_clips ?? '-';
    $note = $productSetting?->note ?? '-';
    $badgeColor = strtoupper(trim((string) ($order->edging ?: $order->color ?: '-')));
    $badgeMaterial = strtoupper(trim((string) ($productSetting?->material_type ?? $order->material_type ?? '-')));
    $quantity = $order->quantity ?? '-';

    $source = (string) ($order->source ?? '');
    $sourceKey = strtolower(trim($source));
    $isPrime = $source === 'Amazon_Prime' || $sourceKey === 'amazon_prime';

    $sourceTag = match (true) {
        $isPrime => 'PRIME (NEXT DAY)',
        in_array($sourceKey, ['amazon_uk', 'amazon'], true), str_starts_with($sourceKey, 'amazon') => '(Amazon)',
        in_array($sourceKey, ['ebay_v2', 'ebay'], true), str_contains($sourceKey, 'ebay') => '(Ebay)',
        $sourceKey === 'tiktok' => '(TikTok)',
        $sourceKey === '' => '(Others)',
        default => '(' . \Illuminate\Support\Str::of($source)->replace(['_', '-'], ' ')->title()->toString() . ')',
    };

    $rawDate = $order->getRawOriginal('date');
    $orderDateDisplay = $rawDate
        ? \Carbon\Carbon::parse($rawDate)->format('d/m/Y H:i')
        : ($order->date ?? '-');

    $barcodeValue = (string) ($order->order_id ?? '');
@endphp

<div class="production-label">

    <div class="pl-section pl-codes">
        <div class="pl-barcode">
            <img
                class="pl-barcode-img"
                src="data:image/png;base64,{{ DNS1D::getBarcodePNG($barcodeValue, 'C128', 1, 55) }}"
                alt="barcode"
            />
            <div class="pl-barcode-text">{{ $barcodeValue }}</div>
        </div>
        <div class="pl-qr">
            <img
                class="pl-qr-img"
                src="data:image/png;base64,{{ DNS2D::getBarcodePNG($barcodeValue, 'QRCODE', 4, 4) }}"
                alt="qr"
            />
        </div>
    </div>

    <div class="pl-section pl-product">
        <div class="pl-product-main">
            <div class="pl-title">{{ $vehicleTitle }}</div>
            <div class="pl-attrs">
                <div>MATS: {{ $mats }}</div>
                <div>CLIPS: {{ $clips }}</div>
                <div>NOTE: {{ $note !== '' && $note !== null ? $note : '-' }}</div>
            </div>
        </div>
        <div class="pl-badges">
            <div class="pl-badge pl-badge-fill">{{ $badgeColor }}</div>
            <div class="pl-badge">{{ $badgeMaterial }}</div>
            <div class="pl-badge pl-badge-qty">{{ $quantity }}</div>
        </div>
    </div>

    <div class="pl-section pl-order">
        <div class="pl-tab">ORDER DETAILS</div>
        <div class="pl-order-body">
            <div class="pl-order-main">
                <div>{{ $order->order_id ?? '-' }}</div>
                <div>{{ $orderDateDisplay }}</div>
                <div class="pl-order-source">{{ $sourceTag }}</div>
                @if (!empty($replacementReason))
                <div class="pl-order-reason">REPLACEMENT: {{ $replacementReason }}</div>
                @endif
                <div class="pl-order-company">{{ config('app.name') }}</div>
            </div>
        </div>
    </div>

    <div class="pl-section pl-note">
        <div class="pl-note-header">INTERNAL NOTE</div>
        <div class="pl-note-body"></div>
    </div>
</div>

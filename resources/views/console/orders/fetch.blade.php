<table class="users-datatable table border-top">
    <thead>
        <tr>
            <th><input type="checkbox" class="form-check-input" id="select-all"></th>
            <th>Sr#</th>
            <th>Order Id</th>
            <th>Order Item Id</th>
            <th>Product</th>
            <th>Quantity</th>
            <th>Recipient Name</th>
            <th class="text-truncate">Order Date</th>
            <th>City</th>
            <th>Status</th>
            <th class="cell-fit">Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($orders as $order)
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input row-checkbox" value="{{ $order->id }}"
                        data-order-id="{{ $order->order_id }}" data-design-type="{{ $order->order_item_id }}"
                        data-product="{{ $order->make_model }}">
                </td>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $order->order_id }}</td>
                <td>{{ $order->order_item_id }}</td>
                <td>
                    <div class="product-ellipsis">
                        {{ $order->make_model }}
                    </div>
                </td>
                <td>{{ $order->quantity }}</td>
                <td>{{ $order->recipient_name }}</td>
                <td>{{ $order->order_date }}</td>
                <td>{{ $order->address_city }}</td>
                <td>{{ $order->status }}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <a href="javascript:;"
                            class="btn btn-icon btn-text-secondary waves-effect waves-light rounded-pill delete-order"
                            data-url="{{ route('console.orders.destroy', $order->id) }}" data-bs-toggle="tooltip"
                            data-bs-placement="top" aria-label="Delete" data-bs-original-title="Delete">
                            <i class="ti ti-trash mx-2 ti-md"></i>
                        </a>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

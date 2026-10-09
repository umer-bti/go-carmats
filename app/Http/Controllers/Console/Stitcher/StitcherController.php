<?php

namespace App\Http\Controllers\Console\Stitcher;

use App\Http\Controllers\Controller;
use App\Http\Concerns\VerifiesAccessPassword;
use App\Models\Order;
use App\Models\Stitcher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class StitcherController extends Controller
{
    use VerifiesAccessPassword;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            
            // if (! session('stitchers_access_verified', false)) {
            //     return response()->json(['message' => 'Unauthorized'], 401);
            // }

            $startInput = $request->input('stitcher_scan_start');
            $endInput = $request->input('stitcher_scan_end');

            if ($startInput && $endInput) {
                $startDate = Carbon::parse($startInput)->startOfDay();
                $endDate = Carbon::parse($endInput)->endOfDay();
            } else {
                $startDate = now()->startOfMonth()->startOfDay();
                $endDate = now()->endOfMonth()->endOfDay();
            }

            if ($startDate->gt($endDate)) {
                [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
            }

            $query = Stitcher::query()->withCount([
                'orders' => function ($q) use ($startDate, $endDate) {
                    $q->whereNotNull('scan_time')
                        ->whereBetween('scan_time', [$startDate, $endDate]);
                },
            ]);

            return DataTables::of($query)
                ->addIndexColumn()
                ->orderColumn('orders_count', function ($query, $order) {
                    $query->orderBy('orders_count', $order);
                })
                ->addColumn('action', function (Stitcher $row) {
                    $buttons = [];
                    if (auth()->user()->can('edit stitchers')) {
                        $nameAttr = htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8');
                        $buttons[] = '<button type="button" class="btn btn-sm btn-primary stitcher-modal-open" data-bs-toggle="modal" data-bs-target="#stitcherFormModal" data-mode="edit" data-id="'
                            .$row->id
                            .'" data-name="'
                            .$nameAttr
                            .'">Edit</button>';
                        $buttons[] = '<button type="button" class="btn btn-sm btn-success stitcher-assign-open" data-bs-toggle="modal" data-bs-target="#stitcherAssignOrderModal" data-id="'
                            .$row->id
                            .'" data-name="'
                            .$nameAttr
                            .'">Assign/Reassign Order</button>';
                    }
                    if (auth()->user()->can('delete stitchers')) {
                        $nameAttr = htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8');
                        $buttons[] = '<button type="button" class="btn btn-sm btn-outline-warning stitcher-unassign-btn" data-id="'
                            .$row->id
                            .'" data-name="'
                            .$nameAttr
                            .'">Reset</button>';
                        $buttons[] = '<button type="button" class="btn btn-sm btn-danger stitcher-delete-btn" data-id="'
                            .$row->id
                            .'" data-name="'
                            .$nameAttr
                            .'">Delete</button>';
                    }

                    if ($buttons === []) {
                        return '<span class="text-muted">—</span>';
                    }

                    return '<div class="d-flex flex-row flex-nowrap align-items-center gap-1 stitcher-row-actions">'.implode('', $buttons).'</div>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('console.stitchers.index');
    }

    /**
     * Full-page gate (same pattern as Settings) – used when Stitchers URL is opened directly.
     */
    public function gate(Request $request)
    {
        
        $returnUrl = $request->query('return', route('console.stitchers.index'));

        return view('console.stitchers.gate', compact('returnUrl'));
    }

    /**
     * Verify stitchers access password and set session.
     */
    public function verifyPassword(Request $request)
    {
        $password = config('settings.access_password', '');
        if ($password === '' || $request->input('password') !== $password) {
            return response()->json(['success' => false, 'message' => 'Incorrect password. Access denied.'], 401);
        }
        session(['stitchers_access_verified' => true]);

        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:stitchers,name'],
        ]);

        Stitcher::create([
            'name' => trim($validated['name']),
        ]);

        return response()->json(['success' => true, 'message' => 'Stitcher created.']);
    }

    public function update(Request $request, Stitcher $stitcher)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stitchers', 'name')->ignore($stitcher->id),
            ],
        ]);

        $stitcher->update([
            'name' => trim($validated['name']),
        ]);

        return response()->json(['success' => true, 'message' => 'Stitcher updated.']);
    }

    public function destroy(Request $request, Stitcher $stitcher)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if ($denied = $this->denyUnlessAccessPassword($request)) {
            return $denied;
        }

        $stitcher->delete();

        return response()->json(['success' => true, 'message' => 'Stitcher deleted.']);
    }

    /**
     * Link order line(s) to this stitcher by marketplace order_id.
     * stitcher_id is always set; scan_time is set to now only when it is currently null.
     * Multiple DB rows may share the same order_id (one per item): then the client must pass line_ids with exactly one id.
     */
    public function assignOrder(Request $request, Stitcher $stitcher)
    {
    
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:255'],
            'line_ids' => ['nullable', 'array'],
            'line_ids.*' => ['integer'],
        ]);

        $needle = trim($validated['order_id']);
        $orders = Order::query()
            ->where('order_id', $needle)
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No order found for that ID.',
            ], 404);
        }

        $allowedIds = $orders->pluck('id')->all();
        $requestedIds = collect($request->input('line_ids', []))
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($orders->count() === 1) {
            $only = $orders->first();
            if (count($requestedIds) > 0 && ! in_array((int) $only->id, $requestedIds, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid item selection for this order.',
                ], 422);
            }
            if ($this->isAlreadyAssignedToThisStitcher($only, $stitcher)) {
                return response()->json([
                    'success' => true,
                    'assigned' => false,
                    'noop' => true,
                    'message' => 'This order is already assigned to the selected stitcher. Nothing to change.',
                ]);
            }
            $this->assignOrdersToStitcher(collect([$only]), $stitcher);

            return response()->json([
                'success' => true,
                'assigned' => true,
                'message' => 'Order assigned successfully.',
            ]);
        }

        if (count($requestedIds) === 0) {
            return response()->json([
                'success' => true,
                'assigned' => false,
                'requires_selection' => true,
                'message' => 'This order has more than one item. Select the item to assign, then press Assign again.',
                'lines' => $orders->map(fn (Order $o) => [
                    'id' => $o->id,
                    'label' => $this->orderLineLabel($o),
                    'already_assigned_here' => $this->isAlreadyAssignedToThisStitcher($o, $stitcher),
                ])->values(),
            ]);
        }

        if (count($requestedIds) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Select exactly one item.',
            ], 422);
        }

        $chosenId = $requestedIds[0];
        if (! in_array($chosenId, $allowedIds, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid item selection for this order.',
            ], 422);
        }

        $picked = $orders->firstWhere('id', $chosenId);
        if ($picked === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid item selection for this order.',
            ], 422);
        }
        if ($this->isAlreadyAssignedToThisStitcher($picked, $stitcher)) {
            return response()->json([
                'success' => true,
                'assigned' => false,
                'noop' => true,
                'message' => 'This order is already assigned to the selected stitcher. Nothing to change.',
            ]);
        }
        $this->assignOrdersToStitcher(collect([$picked]), $stitcher);

        return response()->json([
            'success' => true,
            'assigned' => true,
            'message' => 'Order assigned successfully.',
        ]);
    }

    /**
     * True when this order row is already linked to the given stitcher (idempotent assign / no-op).
     */
    private function isAlreadyAssignedToThisStitcher(Order $order, Stitcher $stitcher): bool
    {
        return $order->stitcher_id !== null
            && (int) $order->stitcher_id === (int) $stitcher->id;
    }

    private function assignOrdersToStitcher(Collection $orders, Stitcher $stitcher): void
    {
        $now = now();
        foreach ($orders as $order) {
            $data = ['stitcher_id' => $stitcher->id];
            if ($order->scan_time === null) {
                $data['scan_time'] = $now;
            }
            $order->update($data);
        }
    }

    private function orderLineLabel(Order $order): string
    {
        $parts = array_filter([
            $order->make_model,
            $order->order_item_id,
        ]);
        $label = implode(' · ', $parts);

        return $label !== '' ? $label : ('Item #'.$order->id);
    }

    /**
     * Clear stitcher_id for every order currently linked to this stitcher.
     */
    public function unassignOrders(Request $request, Stitcher $stitcher)
    {
        $count = Order::query()->where('stitcher_id', $stitcher->id)->update(['stitcher_id' => null]);

        return response()->json([
            'success' => true,
            'message' => $count === 0
                ? 'No orders were assigned to this stitcher.'
                : ($count === 1
                    ? '1 order unassigned.'
                    : $count.' orders unassigned.'),
        ]);
    }
}

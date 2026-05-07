<?php

namespace App\Http\Controllers;

use App\Jobs\PublishOrderEvent;
use App\Models\Order;
use App\Services\UserServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private UserServiceClient $userClient) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->query('user_id'))
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load('items'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'                   => 'required|integer',
            'items'                     => 'required|array|min:1',
            'items.*.product_id'        => 'required|integer',
            'items.*.product_name'      => 'required|string',
            'items.*.quantity'          => 'required|integer|min:1',
            'items.*.unit_price'        => 'required|numeric|min:0',
            'shipping_address'          => 'required|array',
            'shipping_address.street'   => 'required|string',
            'shipping_address.city'     => 'required|string',
            'shipping_address.postcode' => 'required|string',
            'shipping_address.country'  => 'required|string',
        ]);

        // Verify user exists via REST call to User Service
        $user = $this->userClient->getUser($validated['user_id']);

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 422);
        }

        $order = DB::transaction(function () use ($validated) {
            $totalAmount = collect($validated['items'])
                ->sum(fn($item) => $item['quantity'] * $item['unit_price']);

            $order = Order::create([
                'user_id'          => $validated['user_id'],
                'status'           => 'pending',
                'total_amount'     => $totalAmount,
                'shipping_address' => $validated['shipping_address'],
            ]);

            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'subtotal'     => $item['quantity'] * $item['unit_price'],
                ]);
            }

            return $order;
        });

        // Publish order.created event to RabbitMQ
        PublishOrderEvent::dispatch([
            'event'            => 'order.created',
            'order_id'         => $order->id,
            'user_id'          => $order->user_id,
            'user_email'       => $user['email'],
            'user_name'        => $user['name'],
            'total_amount'     => $order->total_amount,
            'shipping_address' => $order->shipping_address,
        ]);

        return response()->json($order->load('items'), 201);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $oldStatus = $order->status;
        $order->update($validated);

        // Publish status change event
        PublishOrderEvent::dispatch([
            'event'      => 'order.status_changed',
            'order_id'   => $order->id,
            'user_id'    => $order->user_id,
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
        ]);

        return response()->json($order);
    }
}

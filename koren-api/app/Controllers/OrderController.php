<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\DeliverySlot;
use App\Services\OrderService;
use Vine\Core\Request;
use Vine\Core\Response;
use Vine\Support\Validator;

class OrderController
{
    private OrderService $service;

    public function __construct()
    {
        $this->service = new OrderService();
    }

    public function store(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'buyer' => 'required|array',
            'items' => 'required|array',
        ]);

        if ($v->fails()) {
            return Response::error('VALIDATION_ERROR', 'Validation failed', 422, $v->errors());
        }

        $buyer = $request->input('buyer');
        if (empty($buyer['name']) || empty($buyer['phone'])) {
            return Response::error('VALIDATION_ERROR', 'buyer.name and buyer.phone are required', 422);
        }

        if (empty($request->input('items'))) {
            return Response::error('VALIDATION_ERROR', 'items cannot be empty', 422);
        }

        $userId = $request->user() ? $request->user()['sub'] : null;

        try {
            $order = $this->service->create($request->all(), $userId);

            $delivery = null;
            if (!empty($order['delivery_slot_id'])) {
                $slot = DeliverySlot::find((int) $order['delivery_slot_id']);
                if ($slot) {
                    $delivery = [
                        'slot'    => $slot['time_range'],
                        'date'    => $slot['date'],
                        'address' => $order['delivery_address'],
                    ];
                }
            }

            return Response::success([
                'order_id'           => (int) $order['id'],
                'status'             => $order['status'],
                'subtotal'           => (float) $order['subtotal'],
                'discount'           => (float) $order['discount'],
                'final_total'        => (float) $order['final_total'],
                'points_earned'      => $order['points_earned'],
                'delivery'           => $delivery,
                'tracking_token'     => $order['tracking_token'],
                'estimated_delivery' => $delivery ? $delivery['date'] . 'T' . explode('-', $delivery['slot'])[1] . ':00Z' : null,
                'message'            => 'Order received. Farmer will confirm within 2 hours.',
            ], 201);

        } catch (\RuntimeException $e) {
            $parts = explode(':', $e->getMessage());
            $code  = $parts[0];

            return match($code) {
                'INSUFFICIENT_STOCK'        => Response::error($code, "Product #{$parts[1]} has only {$parts[2]} units in stock", 422, ['product_id' => (int) $parts[1], 'available' => (int) $parts[2]]),
                'INVALID_REDEMPTION_AMOUNT' => Response::error($code, 'Points must be a multiple of 100', 422),
                'INSUFFICIENT_POINTS'       => Response::error($code, 'Not enough points', 422),
                default                     => throw $e,
            };
        }
    }

    public function publicTracking(Request $request): Response
    {
        $orderId = (int) $request->params['id'];
        $token   = $request->query('token');
        $phone   = $request->query('phone');

        $order = Order::find($orderId);

        if (!$order) {
            return Response::notFound('Order not found');
        }

        if ($token && $order['tracking_token'] !== $token) {
            return Response::error('INVALID_TRACKING_TOKEN', 'Tracking token mismatch', 403);
        } elseif ($phone && $order['buyer_phone'] !== $phone) {
            return Response::error('INVALID_TRACKING_TOKEN', 'Phone does not match', 403);
        } elseif (!$token && !$phone) {
            return Response::error('VALIDATION_ERROR', 'Provide token or phone', 400);
        }

        return Response::success($this->buildTracking($order));
    }

    private function buildTracking(array $order): array
    {
        $statuses   = ['pending', 'confirmed', 'packed', 'in_transit', 'delivered'];
        $currentIdx = array_search($order['status'], $statuses);

        $timeline = [
            ['status' => 'pending',    'label' => 'Order received', 'done' => $currentIdx >= 0],
            ['status' => 'confirmed',  'label' => 'Confirmed',       'done' => $currentIdx >= 1],
            ['status' => 'packed',     'label' => 'Packed',          'done' => $currentIdx >= 2],
            ['status' => 'in_transit', 'label' => 'On the way',      'done' => $currentIdx >= 3],
            ['status' => 'delivered',  'label' => 'Delivered',       'done' => $currentIdx >= 4],
        ];

        $result = [
            'order_id'    => (int) $order['id'],
            'status'      => $order['status'],
            'timeline'    => $timeline,
            'destination' => [
                'label' => $order['delivery_address'],
                'lat'   => $order['delivery_lat'] ? (float) $order['delivery_lat'] : null,
                'lng'   => $order['delivery_lng'] ? (float) $order['delivery_lng'] : null,
            ],
        ];

        if ($order['status'] === 'in_transit' && !empty($order['courier_lat'])) {
            $result['courier'] = [
                'current_location' => [
                    'lat'        => (float) $order['courier_lat'],
                    'lng'        => (float) $order['courier_lng'],
                    'updated_at' => $order['courier_updated_at'],
                ],
            ];
        }

        return $result;
    }
}

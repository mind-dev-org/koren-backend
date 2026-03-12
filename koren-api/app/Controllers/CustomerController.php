<?php

namespace App\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Vine\Core\Request;
use Vine\Core\Response;
use Vine\Database\Connection;
use Vine\Support\Validator;

class CustomerController
{
    public function profile(Request $request): Response
    {
        $userId = $request->user()['sub'];

        $user = Connection::getInstance()->selectOne(
            "SELECT u.id, u.name, u.email, u.phone, u.avatar_url,
             u.address_city, u.address_street, u.address_lat, u.address_lng,
             la.balance as points_balance, la.tier, la.total_earned, u.created_at,
             (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND status != 'cancelled') as total_orders,
             (SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = u.id AND status = 'delivered') as total_spent,
             (SELECT COUNT(*) FROM subscriptions WHERE user_id = u.id AND status = 'active') as active_subscriptions
             FROM users u
             LEFT JOIN loyalty_accounts la ON la.user_id = u.id
             WHERE u.id = :id",
            [':id' => $userId]
        );

        $tier     = $user['tier'] ?? 'sprout';
        $tierCfg  = $this->tierConfig();
        $current  = $tierCfg[$tier] ?? $tierCfg['sprout'];
        $totalEarned = (int) ($user['total_earned'] ?? 0);
        $pointsToNext = $current['next_min'] ? $current['next_min'] - $totalEarned : null;

        return Response::success([
            'id'         => (int) $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'phone'      => $user['phone'],
            'avatar_url' => $user['avatar_url'],
            'address'    => [
                'city'   => $user['address_city'],
                'street' => $user['address_street'],
                'lat'    => $user['address_lat'] ? (float) $user['address_lat'] : null,
                'lng'    => $user['address_lng'] ? (float) $user['address_lng'] : null,
            ],
            'loyalty'    => [
                'points_balance' => (int) ($user['points_balance'] ?? 0),
                'tier'           => $tier,
                'next_tier'      => $current['next'],
                'points_to_next' => $pointsToNext,
                'total_earned'   => $totalEarned,
            ],
            'stats'      => [
                'total_orders'         => (int) ($user['total_orders'] ?? 0),
                'total_spent'          => (float) ($user['total_spent'] ?? 0),
                'active_subscriptions' => (int) ($user['active_subscriptions'] ?? 0),
            ],
            'created_at' => $user['created_at'],
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $userId  = $request->user()['sub'];
        $allowed = ['name', 'phone', 'avatar_url', 'address_city', 'address_street', 'address_lat', 'address_lng'];
        $data    = $request->only($allowed);

        if (!empty($data)) {
            User::update($userId, $data);
        }

        return $this->profile($request);
    }

    public function deleteAccount(Request $request): Response
    {
        $userId = $request->user()['sub'];

        User::update($userId, [
            'email' => 'deleted_' . $userId . '_' . time() . '@deleted.invalid',
            'role'  => 'deleted',
        ]);

        return Response::success(['message' => 'Account deleted successfully']);
    }

    public function orders(Request $request): Response
    {
        $userId  = $request->user()['sub'];
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 10);
        $status  = $request->query('status');
        $db      = Connection::getInstance();

        $whereStatus = $status ? "AND o.status = :status" : "";
        $params      = [':uid' => $userId];
        if ($status) {
            $params[':status'] = $status;
        }

        $total  = (int) ($db->selectOne(
            "SELECT COUNT(DISTINCT o.id) as cnt FROM orders o WHERE o.user_id = :uid $whereStatus",
            $params
        )['cnt'] ?? 0);

        $offset = ($page - 1) * $perPage;

        $orders = $db->select(
            "SELECT o.id, o.status, o.subtotal, o.discount, o.total, o.tracking_token, o.created_at,
             COUNT(oi.id) as items_count
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = :uid $whereStatus
             GROUP BY o.id
             ORDER BY o.created_at DESC
             LIMIT " . (int) $perPage . " OFFSET " . (int) $offset,
            $params
        );

        $orderIds   = array_column($orders, 'id');
        $previewMap = [];

        if (!empty($orderIds)) {
            $in    = implode(',', array_map('intval', $orderIds));
            $items = $db->select(
                "SELECT oi.order_id, p.name as product_name, p.image_url, oi.qty
                 FROM order_items oi
                 JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id IN ($in)"
            );
            foreach ($items as $item) {
                if (!isset($previewMap[$item['order_id']]) || count($previewMap[$item['order_id']]) < 3) {
                    $previewMap[$item['order_id']][] = [
                        'product_name' => $item['product_name'],
                        'image_url'    => $item['image_url'],
                        'qty'          => (int) $item['qty'],
                    ];
                }
            }
        }

        $orders = array_map(function($order) use ($previewMap) {
            return array_merge($order, [
                'status_label'  => $this->statusLabel($order['status']),
                'items_count'   => (int) $order['items_count'],
                'items_preview' => $previewMap[$order['id']] ?? [],
            ]);
        }, $orders);

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return Response::collection($orders, [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ]);
    }

    public function orderDetail(Request $request): Response
    {
        $userId  = $request->user()['sub'];
        $orderId = (int) $request->params['id'];

        $order = Order::query()->where('id', '=', $orderId)->where('user_id', '=', $userId)->first();
        if (!$order) {
            return Response::notFound('Order not found');
        }

        $items = Connection::getInstance()->select(
            "SELECT oi.*, p.name as product_name, p.image_url FROM order_items oi
             JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :id",
            [':id' => $orderId]
        );

        return Response::success(array_merge($order, [
            'status_label' => $this->statusLabel($order['status']),
            'items'        => $items,
        ]));
    }

    public function tracking(Request $request): Response
    {
        $userId  = $request->user()['sub'];
        $orderId = (int) $request->params['id'];

        $order = Order::query()->where('id', '=', $orderId)->where('user_id', '=', $userId)->first();
        if (!$order) {
            return Response::notFound('Order not found');
        }

        return Response::success($this->buildTracking($order));
    }

    public function points(Request $request): Response
    {
        $userId  = $request->user()['sub'];
        $loyalty = LoyaltyAccount::findByUser($userId);

        if (!$loyalty) {
            return Response::notFound('Loyalty account not found');
        }

        $tierMeta    = $this->tierMeta();
        $tier        = $loyalty['tier'];
        $current     = $tierMeta[$tier] ?? $tierMeta['sprout'];
        $totalEarned = (int) $loyalty['total_earned'];

        $progressPercent = 100;
        if ($current['next_min']) {
            $range           = $current['next_min'] - $current['min'];
            $progress        = $totalEarned - $current['min'];
            $progressPercent = $range > 0 ? min(100, (int) round($progress / $range * 100)) : 100;
        }

        $nextTierData = null;
        if ($current['next']) {
            $nextMeta     = $tierMeta[$current['next']];
            $nextTierData = [
                'name'          => $current['next'],
                'label'         => $nextMeta['label'],
                'points_needed' => $current['next_min'] - $totalEarned,
                'perks'         => $nextMeta['perks'],
            ];
        }

        return Response::success([
            'balance'          => (int) $loyalty['balance'],
            'tier'             => [
                'current'    => $tier,
                'label'      => $current['label'],
                'min_points' => $current['min'],
                'perks'      => $current['perks'],
            ],
            'next_tier'        => $nextTierData,
            'progress_percent' => $progressPercent,
            'expires_at'       => null,
        ]);
    }

    public function pointsHistory(Request $request): Response
    {
        $userId  = $request->user()['sub'];
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $result = LoyaltyTransaction::query()
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->paginate($page, $perPage);

        return Response::collection($result['data'], $result['meta']);
    }

    public function redeemPoints(Request $request): Response
    {
        $userId         = $request->user()['sub'];
        $pointsToRedeem = (int) $request->input('points_to_redeem');
        $orderId        = (int) $request->input('order_id');

        if ($pointsToRedeem % 100 !== 0) {
            return Response::error('INVALID_REDEMPTION_AMOUNT', 'Points must be multiple of 100', 422);
        }

        $loyalty = LoyaltyAccount::findByUser($userId);
        if (!$loyalty || $loyalty['balance'] < $pointsToRedeem) {
            return Response::error('INSUFFICIENT_POINTS', 'Not enough points', 422);
        }

        $discount = $pointsToRedeem / 100;

        LoyaltyAccount::query()->where('user_id', '=', $userId)->update([
            'balance' => $loyalty['balance'] - $pointsToRedeem,
        ]);

        $orderNewTotal = null;
        $order         = Order::find($orderId);
        if ($order) {
            $newTotal = max(0, $order['total'] - $discount);
            Order::update($orderId, [
                'discount' => $order['discount'] + $discount,
                'total'    => $newTotal,
            ]);
            $orderNewTotal = $newTotal;
        }

        return Response::success([
            'redeemed_points'  => $pointsToRedeem,
            'discount_applied' => $discount,
            'new_balance'      => $loyalty['balance'] - $pointsToRedeem,
            'order_new_total'  => $orderNewTotal,
        ]);
    }

    public function subscriptions(Request $request): Response
    {
        $userId = $request->user()['sub'];
        $subs   = Subscription::query()->where('user_id', '=', $userId)->get();
        return Response::success($subs);
    }

    public function subscriptionDetail(Request $request): Response
    {
        $subId  = (int) $request->params['id'];
        $userId = $request->user()['sub'];

        $sub = Subscription::query()->where('id', '=', $subId)->where('user_id', '=', $userId)->first();
        if (!$sub) {
            return Response::notFound('Subscription not found');
        }

        $items = Connection::getInstance()->select(
            "SELECT si.*, p.name as product_name, p.price, p.unit, p.image_url
             FROM subscription_items si
             JOIN products p ON p.id = si.product_id
             WHERE si.subscription_id = :sid",
            [':sid' => $subId]
        );

        return Response::success(array_merge($sub, ['items' => $items]));
    }

    public function createSubscription(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'items'    => 'required|array',
            'schedule' => 'required|array',
        ]);

        if ($v->fails()) {
            return Response::error('VALIDATION_ERROR', 'Validation failed', 422, $v->errors());
        }

        $schedule = $request->input('schedule');
        $address  = $request->input('delivery_address', []);

        $sub = Subscription::create([
            'user_id'          => $request->user()['sub'],
            'status'           => 'active',
            'frequency'        => $schedule['frequency'],
            'day_of_week'      => $schedule['day_of_week'] ?? null,
            'day_of_month'     => $schedule['day_of_month'] ?? null,
            'delivery_slot'    => $schedule['delivery_slot'] ?? null,
            'delivery_address' => $address['street'] ?? null,
            'delivery_lat'     => $address['lat'] ?? null,
            'delivery_lng'     => $address['lng'] ?? null,
            'next_delivery_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'note'             => $request->input('note'),
        ]);

        foreach ($request->input('items') as $item) {
            SubscriptionItem::create([
                'subscription_id' => $sub['id'],
                'product_id'      => $item['product_id'],
                'qty'             => $item['qty'],
            ]);
        }

        return Response::success($sub, 201);
    }

    public function updateSubscription(Request $request): Response
    {
        $subId  = (int) $request->params['id'];
        $userId = $request->user()['sub'];
        $sub    = Subscription::query()->where('id', '=', $subId)->where('user_id', '=', $userId)->first();

        if (!$sub) {
            return Response::notFound('Subscription not found');
        }

        $action     = $request->input('action');
        $updateData = match($action) {
            'pause'  => ['status' => 'paused', 'pause_until' => $request->input('pause_until')],
            'resume' => ['status' => 'active',  'pause_until' => null],
            'update' => ['frequency' => $request->input('schedule')['frequency'] ?? $sub['frequency']],
            default  => [],
        };

        Subscription::update($subId, $updateData);

        return Response::success(Subscription::find($subId));
    }

    public function deleteSubscription(Request $request): Response
    {
        $subId  = (int) $request->params['id'];
        $userId = $request->user()['sub'];
        $sub    = Subscription::query()->where('id', '=', $subId)->where('user_id', '=', $userId)->first();

        if (!$sub) {
            return Response::notFound('Subscription not found');
        }

        Subscription::update($subId, ['status' => 'cancelled']);

        return Response::success(['message' => 'Subscription cancelled']);
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

    private function statusLabel(string $status): string
    {
        return match($status) {
            'pending'    => 'Очікує підтвердження',
            'confirmed'  => 'Підтверджено',
            'packed'     => 'Запаковано',
            'in_transit' => 'В дорозі',
            'delivered'  => 'Доставлено',
            'cancelled'  => 'Скасовано',
            default      => $status,
        };
    }

    private function tierConfig(): array
    {
        return [
            'sprout'   => ['next' => 'seedling', 'next_min' => 500],
            'seedling' => ['next' => 'harvest',  'next_min' => 1000],
            'harvest'  => ['next' => 'root',     'next_min' => 2500],
            'root'     => ['next' => null,        'next_min' => null],
        ];
    }

    private function tierMeta(): array
    {
        return [
            'sprout'   => ['label' => 'Sprout 🌱',   'min' => 0,    'next' => 'seedling', 'next_min' => 500,  'perks' => []],
            'seedling' => ['label' => 'Seedling 🌱',  'min' => 500,  'next' => 'harvest',  'next_min' => 1000, 'perks' => ['Пріоритетна доставка у вт/чт', '+5% балів за кожне замовлення']],
            'harvest'  => ['label' => 'Harvest 🌾',   'min' => 1000, 'next' => 'root',     'next_min' => 2500, 'perks' => ['Безкоштовна доставка 1 раз/міс', '+10% балів']],
            'root'     => ['label' => 'Root 🌿',      'min' => 2500, 'next' => null,        'next_min' => null, 'perks' => ['VIP підтримка', '+15% балів', 'Безкоштовна доставка']],
        ];
    }
}

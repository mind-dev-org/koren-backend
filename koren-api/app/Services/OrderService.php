<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\DeliverySlot;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use Vine\Database\Connection;

class OrderService
{
    public function create(array $data, ?int $userId): array
    {
        return Connection::getInstance()->transaction(function($db) use ($data, $userId) {
            $subtotal = 0;
            $itemsData = [];

            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new \RuntimeException("PRODUCT_NOT_FOUND:{$item['product_id']}");
                }

                if ($product['stock_qty'] < $item['qty']) {
                    throw new \RuntimeException("INSUFFICIENT_STOCK:{$item['product_id']}:{$product['stock_qty']}");
                }

                $subtotal += $product['price'] * $item['qty'];
                $itemsData[] = [
                    'product'    => $product,
                    'qty'        => $item['qty'],
                    'unit_price' => $product['price'],
                ];
            }

            $discount = 0;
            if ($userId && !empty($data['redeem_points'])) {
                $loyalty = LoyaltyAccount::findByUser($userId);
                $pointsToRedeem = (int) $data['redeem_points'];

                if ($pointsToRedeem % 100 !== 0) {
                    throw new \RuntimeException('INVALID_REDEMPTION_AMOUNT');
                }
                if ($loyalty && $loyalty['balance'] < $pointsToRedeem) {
                    throw new \RuntimeException('INSUFFICIENT_POINTS');
                }

                $discount = $pointsToRedeem / 100;
            }

            $total = max(0, $subtotal - $discount);

            $trackingToken = bin2hex(random_bytes(16));

            $order = Order::create([
                'user_id'          => $userId,
                'buyer_name'       => $data['buyer']['name'],
                'buyer_phone'      => $data['buyer']['phone'],
                'buyer_email'      => $data['buyer']['email'] ?? null,
                'delivery_slot_id' => $data['delivery_slot_id'] ?? null,
                'delivery_address' => $data['delivery_address']['street'] ?? null,
                'delivery_lat'     => $data['delivery_address']['lat'] ?? null,
                'delivery_lng'     => $data['delivery_address']['lng'] ?? null,
                'subtotal'         => $subtotal,
                'discount'         => $discount,
                'total'            => $total,
                'redeem_points'    => $data['redeem_points'] ?? 0,
                'tracking_token'   => $trackingToken,
                'note'             => $data['note'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                OrderItem::create([
                    'order_id'   => $order['id'],
                    'product_id' => $item['product']['id'],
                    'qty'        => $item['qty'],
                    'unit_price' => $item['unit_price'],
                ]);

                Product::query()
                    ->where('id', '=', $item['product']['id'])
                    ->update(['stock_qty' => $item['product']['stock_qty'] - $item['qty']]);
            }

            $pointsEarned = 0;
            if ($userId) {
                $pointsEarned = (int) floor($total * 10);
                $this->addPoints($userId, $order['id'], $pointsEarned, 'earned', "Order #{$order['id']}");

                if (!empty($data['redeem_points'])) {
                    $this->deductPoints($userId, $order['id'], (int) $data['redeem_points'], 'redeemed', "Redeemed for order #{$order['id']}");
                }
            }

            return array_merge($order, [
                'points_earned'   => $pointsEarned,
                'tracking_token'  => $trackingToken,
                'final_total'     => $total,
            ]);
        });
    }

    public function updateStatus(int $orderId, string $status, int $farmerId, array $extra = []): array
    {
        $order = Order::findOrFail($orderId);

        $updateData = ['status' => $status];

        if ($status === 'in_transit' && !empty($extra['location'])) {
            $updateData['courier_lat'] = $extra['location']['lat'];
            $updateData['courier_lng'] = $extra['location']['lng'];
            $updateData['courier_updated_at'] = date('Y-m-d H:i:s');
        }

        Order::update($orderId, $updateData);

        return Order::findOrFail($orderId);
    }

    private function addPoints(int $userId, int $orderId, int $amount, string $type, string $desc): void
    {
        $loyalty = LoyaltyAccount::findByUser($userId);
        if (!$loyalty) return;

        $newBalance = $loyalty['balance'] + $amount;
        $newTotal = $loyalty['total_earned'] + $amount;
        $tier = $this->calculateTier($newTotal);

        LoyaltyAccount::query()->where('user_id', '=', $userId)->update([
            'balance'      => $newBalance,
            'total_earned' => $newTotal,
            'tier'         => $tier,
        ]);

        LoyaltyTransaction::create([
            'user_id'     => $userId,
            'order_id'    => $orderId,
            'type'        => $type,
            'amount'      => $amount,
            'description' => $desc,
        ]);
    }

    private function deductPoints(int $userId, int $orderId, int $amount, string $type, string $desc): void
    {
        $loyalty = LoyaltyAccount::findByUser($userId);
        if (!$loyalty) return;

        LoyaltyAccount::query()->where('user_id', '=', $userId)->update([
            'balance' => $loyalty['balance'] - $amount,
        ]);

        LoyaltyTransaction::create([
            'user_id'     => $userId,
            'order_id'    => $orderId,
            'type'        => $type,
            'amount'      => -$amount,
            'description' => $desc,
        ]);
    }

    private function calculateTier(int $totalEarned): string
    {
        if ($totalEarned >= 2500) return 'root';
        if ($totalEarned >= 1000) return 'harvest';
        if ($totalEarned >= 500)  return 'seedling';
        return 'sprout';
    }
}

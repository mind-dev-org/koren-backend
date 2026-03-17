<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? '');
    }

    public function createPaymentIntent(int $orderId, ?int $userId): array
    {
        $order = Order::find($orderId);

        if (!$order) {
            throw new \RuntimeException('ORDER_NOT_FOUND');
        }

        if ($order['status'] !== 'pending') {
            throw new \RuntimeException('ORDER_NOT_PAYABLE');
        }

        $existing = Payment::findByOrder($orderId);
        if ($existing && $existing['status'] === 'succeeded') {
            throw new \RuntimeException('ORDER_ALREADY_PAID');
        }

        $amountCents = (int) round((float) $order['total'] * 100);

        $intent = PaymentIntent::create([
            'amount'   => $amountCents,
            'currency' => 'eur',
            'metadata' => [
                'order_id' => $orderId,
                'user_id'  => $userId ?? 'guest',
            ],
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        if ($existing) {
            Payment::update($existing['id'], [
                'stripe_payment_intent_id' => $intent->id,
                'stripe_client_secret'     => $intent->client_secret,
                'status'                   => 'pending',
            ]);
        } else {
            Payment::create([
                'order_id'                   => $orderId,
                'user_id'                    => $userId,
                'stripe_payment_intent_id'   => $intent->id,
                'stripe_client_secret'       => $intent->client_secret,
                'amount'                     => $order['total'],
                'currency'                   => 'eur',
                'status'                     => 'pending',
            ]);
        }

        return [
            'payment_intent_id' => $intent->id,
            'client_secret'     => $intent->client_secret,
            'amount'            => $order['total'],
            'currency'          => 'eur',
            'order_id'          => $orderId,
        ];
    }

    public function handleWebhook(string $payload, string $sigHeader): void
    {
        $secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            throw new \RuntimeException('WEBHOOK_SIGNATURE_INVALID');
        }

        match ($event->type) {
            'payment_intent.succeeded'               => $this->onPaymentSucceeded($event->data->object),
            'payment_intent.payment_failed'          => $this->onPaymentFailed($event->data->object),
            'payment_intent.canceled'                => $this->onPaymentCanceled($event->data->object),
            default                                  => null,
        };
    }

    private function onPaymentSucceeded(object $intent): void
    {
        $payment = Payment::findByIntent($intent->id);
        if (!$payment) return;

        Payment::update($payment['id'], ['status' => 'succeeded']);
        Order::update($payment['order_id'], ['status' => 'confirmed']);
    }

    private function onPaymentFailed(object $intent): void
    {
        $payment = Payment::findByIntent($intent->id);
        if (!$payment) return;

        Payment::update($payment['id'], ['status' => 'failed']);
    }

    private function onPaymentCanceled(object $intent): void
    {
        $payment = Payment::findByIntent($intent->id);
        if (!$payment) return;

        Payment::update($payment['id'], ['status' => 'cancelled']);
    }
}

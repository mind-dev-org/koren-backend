<?php

namespace App\Controllers;

use App\Models\Payment;
use App\Services\StripeService;
use Vine\Core\Request;
use Vine\Core\Response;
use Vine\Support\Validator;

class PaymentController
{
    private StripeService $stripe;

    public function __construct()
    {
        $this->stripe = new StripeService();
    }

    public function createIntent(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'order_id' => 'required|int',
        ]);

        if ($v->fails()) {
            return Response::error('VALIDATION_ERROR', 'Validation failed', 422, $v->errors());
        }

        $userId = $request->user() ? $request->user()['sub'] : null;

        try {
            $result = $this->stripe->createPaymentIntent(
                (int) $request->input('order_id'),
                $userId
            );
            return Response::success($result, 201);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'ORDER_NOT_FOUND'    => Response::notFound('Order not found'),
                'ORDER_NOT_PAYABLE'  => Response::error('ORDER_NOT_PAYABLE', 'Order cannot be paid in current status', 422),
                'ORDER_ALREADY_PAID' => Response::error('ORDER_ALREADY_PAID', 'Order has already been paid', 409),
                default              => throw $e,
            };
        }
    }

    public function webhook(Request $request): Response
    {
        $payload   = file_get_contents('php://input');
        $sigHeader = $request->header('stripe-signature', '');

        if (!$sigHeader) {
            return Response::error('WEBHOOK_SIGNATURE_INVALID', 'Missing Stripe signature', 400);
        }

        try {
            $this->stripe->handleWebhook($payload, $sigHeader);
            return Response::success(['received' => true]);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'WEBHOOK_SIGNATURE_INVALID') {
                return Response::error('WEBHOOK_SIGNATURE_INVALID', 'Invalid webhook signature', 400);
            }
            throw $e;
        }
    }

    public function status(Request $request): Response
    {
        $orderId = (int) $request->params['order_id'];
        $userId  = $request->user()['sub'];

        $payment = Payment::findByOrder($orderId);

        if (!$payment) {
            return Response::notFound('No payment found for this order');
        }

        if ($payment['user_id'] && (int) $payment['user_id'] !== $userId) {
            return Response::forbidden('Access denied');
        }

        return Response::success([
            'order_id'   => (int) $payment['order_id'],
            'status'     => $payment['status'],
            'amount'     => (float) $payment['amount'],
            'currency'   => $payment['currency'],
            'created_at' => $payment['created_at'],
        ]);
    }

    public function history(Request $request): Response
    {
        $userId = $request->user()['sub'];

        $payments = Payment::query()
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();

        return Response::success($payments);
    }
}

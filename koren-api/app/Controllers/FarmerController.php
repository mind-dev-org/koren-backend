<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\OrderService;
use Vine\Core\Request;
use Vine\Core\Response;
use Vine\Database\Connection;
use Vine\Support\Validator;

class FarmerController
{
    public function index(Request $request): Response
    {
        $farmers = Connection::getInstance()->select(
            "SELECT u.id, u.name, u.avatar_url, fp.farm_name, fp.region, fp.bio_short, fp.years_exp, fp.rating, fp.reviews_count,
             COUNT(p.id) as products_count
             FROM users u
             JOIN farmer_profiles fp ON fp.user_id = u.id
             LEFT JOIN products p ON p.farmer_id = u.id AND p.is_active = TRUE
             WHERE u.role = 'farmer'
             GROUP BY u.id, fp.farm_name, fp.region, fp.bio_short, fp.years_exp, fp.rating, fp.reviews_count"
        );

        return Response::collection($farmers, ['total' => count($farmers)]);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->params['id'];

        $farmer = Connection::getInstance()->selectOne(
            "SELECT u.id, u.name, u.avatar_url, fp.*
             FROM users u JOIN farmer_profiles fp ON fp.user_id = u.id
             WHERE u.id = :id AND u.role = 'farmer'",
            [':id' => $id]
        );

        if (!$farmer) {
            return Response::notFound('Farmer not found');
        }

        return Response::success($farmer);
    }

    public function products(Request $request): Response
    {
        $farmerId = (int) $request->params['id'];
        $products = Product::query()
            ->where('farmer_id', '=', $farmerId)
            ->where('is_active', '=', true)
            ->get();

        return Response::success($products);
    }

    public function myProfile(Request $request): Response
    {
        $userId = $request->user()['sub'];
        $farmer = Connection::getInstance()->selectOne(
            "SELECT u.id, u.name, u.email, u.phone, u.avatar_url, fp.*
             FROM users u JOIN farmer_profiles fp ON fp.user_id = u.id WHERE u.id = :id",
            [':id' => $userId]
        );
        return Response::success($farmer);
    }

    public function updateProfile(Request $request): Response
    {
        $userId = $request->user()['sub'];

        $userData   = $request->only(['name', 'phone', 'avatar_url']);
        $farmerData = $request->only(['farm_name', 'region', 'bio', 'bio_short', 'years_exp']);

        if (!empty($userData)) {
            User::update($userId, $userData);
        }

        if (!empty($farmerData)) {
            FarmerProfile::query()->where('user_id', '=', $userId)->update($farmerData);
        }

        return $this->myProfile($request);
    }

    public function myProducts(Request $request): Response
    {
        $userId   = $request->user()['sub'];
        $products = Product::query()
            ->where('farmer_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();

        return Response::success($products);
    }

    public function addProduct(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'name'        => 'required|string',
            'price'       => 'required|numeric',
            'category_id' => 'required|int',
            'stock_qty'   => 'required|int',
        ]);

        if ($v->fails()) {
            return Response::error('VALIDATION_ERROR', 'Validation failed', 422, $v->errors());
        }

        $data              = $request->only(['name', 'price', 'category_id', 'stock_qty', 'description', 'unit', 'image_url', 'harvested_at']);
        $data['farmer_id'] = $request->user()['sub'];
        $data['slug']      = strtolower(str_replace(' ', '-', $data['name'])) . '-' . time();

        $product = Product::create($data);
        return Response::success($product, 201);
    }

    public function updateProduct(Request $request): Response
    {
        $productId = (int) $request->params['id'];
        $userId    = $request->user()['sub'];

        $product = Product::query()
            ->where('id', '=', $productId)
            ->where('farmer_id', '=', $userId)
            ->first();

        if (!$product) {
            return Response::notFound('Product not found');
        }

        $allowed = ['name', 'price', 'stock_qty', 'description', 'unit', 'image_url', 'harvested_at'];
        Product::update($productId, $request->only($allowed));

        return Response::success(Product::find($productId));
    }

    public function deleteProduct(Request $request): Response
    {
        $productId = (int) $request->params['id'];
        $userId    = $request->user()['sub'];

        $product = Product::query()
            ->where('id', '=', $productId)
            ->where('farmer_id', '=', $userId)
            ->first();

        if (!$product) {
            return Response::notFound('Product not found');
        }

        Product::query()->where('id', '=', $productId)->update(['is_active' => false]);

        return Response::success(['message' => 'Product deactivated']);
    }

    public function myOrders(Request $request): Response
    {
        $userId = $request->user()['sub'];

        $orders = Connection::getInstance()->select(
            "SELECT DISTINCT o.* FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products p ON p.id = oi.product_id
             WHERE p.farmer_id = :fid
             ORDER BY o.created_at DESC",
            [':fid' => $userId]
        );

        return Response::success($orders);
    }

    public function updateOrderStatus(Request $request): Response
    {
        $orderId = (int) $request->params['id'];
        $status  = $request->input('status');

        $allowed = ['confirmed', 'packed', 'in_transit', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed)) {
            return Response::error('VALIDATION_ERROR', 'Invalid status', 422);
        }

        $service = new OrderService();
        $order   = $service->updateStatus($orderId, $status, $request->user()['sub'], $request->all());

        return Response::success([
            'order_id'          => (int) $order['id'],
            'status'            => $order['status'],
            'updated_at'        => date('c'),
            'customer_notified' => true,
        ]);
    }

    public function stats(Request $request): Response
    {
        $userId = $request->user()['sub'];

        $stats = Connection::getInstance()->selectOne(
            "SELECT COUNT(DISTINCT o.id) as total_orders,
             COALESCE(SUM(o.total), 0) as total_revenue,
             COUNT(DISTINCT p.id) as products_count
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products p ON p.id = oi.product_id
             WHERE p.farmer_id = :fid AND o.status != 'cancelled'",
            [':fid' => $userId]
        );

        return Response::success($stats);
    }
}

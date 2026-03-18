<?php

use App\Controllers\AuthController;
use App\Controllers\ProductController;
use App\Controllers\OrderController;
use App\Controllers\FarmerController;
use App\Controllers\CustomerController;
use App\Controllers\DeliveryController;
use App\Controllers\PaymentController;
use App\Middleware\AuthMiddleware;
use App\Middleware\FarmerMiddleware;

$router->group(["prefix" => "/api/v1"], function ($r) {
	$r->get(
		"/health",
		fn($req) => \Vine\Core\Response::success([
			"status" => "ok",
			"version" => "1.0.0",
			"db" => "connected",
			"time" => date("c"),
		]),
	);

	$r->get("/stats", function ($req) {
		$db = \Vine\Database\Connection::getInstance();
		$row = $db->selectOne("SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'farmer') as farmers_count,
            (SELECT COUNT(*) FROM products WHERE is_active = TRUE) as products_count,
            (SELECT COUNT(*) FROM orders WHERE status != 'cancelled') as orders_count,
            (SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'delivered') as total_sold
        ");
		return \Vine\Core\Response::success($row);
	});

	$r->group(["prefix" => "/auth"], function ($r) {
		$r->post("/register", [AuthController::class, "register"]);
		$r->post("/login", [AuthController::class, "login"]);
		$r->post("/logout", [AuthController::class, "logout"]);
		$r->post("/refresh", [AuthController::class, "refresh"]);
		$r->post("/forgot-password", [AuthController::class, "forgotPassword"]);
		$r->post("/reset-password", [AuthController::class, "resetPassword"]);
		$r->post("/farmer/register", [AuthController::class, "registerFarmer"]);
		$r->post("/google", [AuthController::class, "googleLogin"]);
		$r->post("/apple", [AuthController::class, "appleLogin"]);
	});

	$r->get("/products", [ProductController::class, "index"]);
	$r->get("/products/featured", [ProductController::class, "featured"]);
	$r->get("/products/{id}", [ProductController::class, "show"]);
	$r->get("/categories", [ProductController::class, "categories"]);

	$r->get("/farmers", [FarmerController::class, "index"]);
	$r->get("/farmers/{id}", [FarmerController::class, "show"]);
	$r->get("/farmers/{id}/products", [FarmerController::class, "products"]);

	$r->group(
		["prefix" => "/farmer", "middleware" => FarmerMiddleware::class],
		function ($r) {
			$r->get("/profile", [FarmerController::class, "myProfile"]);
			$r->put("/profile", [FarmerController::class, "updateProfile"]);
			$r->get("/products", [FarmerController::class, "myProducts"]);
			$r->post("/products", [FarmerController::class, "addProduct"]);
			$r->put("/products/{id}", [
				FarmerController::class,
				"updateProduct",
			]);
			$r->delete("/products/{id}", [
				FarmerController::class,
				"deleteProduct",
			]);
			$r->get("/orders", [FarmerController::class, "myOrders"]);
			$r->patch("/orders/{id}/status", [
				FarmerController::class,
				"updateOrderStatus",
			]);
			$r->get("/stats", [FarmerController::class, "stats"]);
		},
	);

	$r->post("/orders", [OrderController::class, "store"]);
	$r->get("/orders/{id}/tracking", [
		OrderController::class,
		"publicTracking",
	]);

	$r->group(
		["prefix" => "/me", "middleware" => AuthMiddleware::class],
		function ($r) {
			$r->get("", [CustomerController::class, "profile"]);
			$r->put("", [CustomerController::class, "updateProfile"]);
			$r->delete("", [CustomerController::class, "deleteAccount"]);
			$r->get("/orders", [CustomerController::class, "orders"]);
			$r->get("/orders/{id}", [CustomerController::class, "orderDetail"]);
			$r->get("/orders/{id}/tracking", [
				CustomerController::class,
				"tracking",
			]);
			$r->get("/points", [CustomerController::class, "points"]);
			$r->get("/points/history", [
				CustomerController::class,
				"pointsHistory",
			]);
			$r->post("/points/redeem", [
				CustomerController::class,
				"redeemPoints",
			]);
			$r->get("/subscriptions", [
				CustomerController::class,
				"subscriptions",
			]);
			$r->post("/subscriptions", [
				CustomerController::class,
				"createSubscription",
			]);
			$r->get("/subscriptions/{id}", [
				CustomerController::class,
				"subscriptionDetail",
			]);
			$r->patch("/subscriptions/{id}", [
				CustomerController::class,
				"updateSubscription",
			]);
			$r->delete("/subscriptions/{id}", [
				CustomerController::class,
				"deleteSubscription",
			]);
		},
	);

	$r->get("/delivery/slots", [DeliveryController::class, "slots"]);
	$r->get("/delivery/zones", [DeliveryController::class, "zones"]);

	$r->post("/payments/webhook", [PaymentController::class, "webhook"]);
	$r->post("/payments/create-intent", [
		PaymentController::class,
		"createIntent",
	]);

	$r->group(
		["prefix" => "/me", "middleware" => AuthMiddleware::class],
		function ($r) {
			$r->get("/payments", [PaymentController::class, "history"]);
			$r->get("/payments/{order_id}/status", [
				PaymentController::class,
				"status",
			]);
		},
	);
});

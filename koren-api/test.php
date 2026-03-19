#!/usr/bin/env php
<?php
// --- Config ------------------------------------------------------------------

// $BASE = getenv("API_URL") ?: "https://koren-api.onrender.com/api/v1";
$BASE = getenv("API_URL") ?: "http://localhost:8000/api/v1";

$ts = time();
$customerEmail = "test_customer_{$ts}@test.ua";
$farmerEmail = "test_farmer_{$ts}@test.ua";
$password = "TestPass123!";

// --- State (filled during run) ------------------------------------------------

$state = [
	"customer_token" => null,
	"farmer_token" => null,
	"product_id" => null,
	"order_id" => null,
	"tracking_token" => null,
	"subscription_id" => null,
	"delivery_slot_id" => null,
	"farmer_product_id" => null,
];

// --- HTTP Helper -------------------------------------------------------------

function req(
	string $method,
	string $url,
	array $body = [],
	?string $token = null,
): array {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);

	$headers = ["Content-Type: application/json", "Accept: application/json"];
	if ($token) {
		$headers[] = "Authorization: Bearer $token";
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	if (!empty($body)) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
	}

	$raw = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	$data = json_decode($raw, true) ?? [];
	return ["code" => $code, "body" => $data, "raw" => $raw];
}

// --- Output Helpers -----------------------------------------------------------

$passed = 0;
$failed = 0;
$errors = [];

function color(string $text, string $code): string
{
	return "\033[{$code}m{$text}\033[0m";
}

function pass(string $name): void
{
	global $passed;
	$passed++;
	echo color("  ✓", "32") . "  $name\n";
}

function fail(string $name, string $reason, array $body = []): void
{
	global $failed, $errors;
	$failed++;
	$errors[] = ["name" => $name, "reason" => $reason, "body" => $body];
	echo color("  ✗", "31") . "  $name " . color("→ $reason", "31") . "\n";
}

function section(string $title): void
{
	echo "\n" . color("  $title", "33;1") . "\n";
	echo color("  " . str_repeat("-", 50), "90") . "\n";
}

function assert_ok(
	string $name,
	array $res,
	int $expectCode = 200,
	?callable $check = null,
): void {
	if ($res["code"] !== $expectCode) {
		$msg =
			$res["body"]["message"] ??
			($res["body"]["error"] ?? "HTTP {$res["code"]}");
		fail(
			$name,
			"Expected $expectCode, got {$res["code"]}: $msg",
			$res["body"],
		);
		return;
	}
	if ($check && !$check($res["body"])) {
		fail($name, "Response shape check failed", $res["body"]);
		return;
	}
	pass($name);
}

// --- TESTS -------------------------------------------------------------------

echo "\n" . color(" KOREN API — Test Runner", "36;1") . "\n";
echo color(" " . $BASE, "90") . "\n";

// -- System --------------------------------------------------------------------
section("System");

$r = req("GET", "$BASE/health");
assert_ok(
	"GET /health",
	$r,
	200,
	fn($b) => ($b["data"]["status"] ?? "") === "ok",
);

$r = req("GET", "$BASE/stats");
assert_ok("GET /stats", $r, 200, fn($b) => isset($b["data"]["farmers_count"]));

// -- Auth ----------------------------------------------------------------------
section("Auth");

$r = req("POST", "$BASE/auth/register", [
	"name" => "Test Customer",
	"email" => $customerEmail,
	"password" => $password,
]);
assert_ok(
	"POST /auth/register",
	$r,
	201,
	fn($b) => isset($b["data"]["access_token"]),
);
$state["customer_token"] = $r["body"]["data"]["access_token"] ?? null;

$r = req("POST", "$BASE/auth/register", [
	"name" => "Test Customer",
	"email" => $customerEmail,
	"password" => $password,
]);
assert_ok("POST /auth/register → 409 duplicate email", $r, 409);

$r = req("POST", "$BASE/auth/register", ["email" => "bad"]);
assert_ok("POST /auth/register → 422 validation", $r, 422);

$r = req("POST", "$BASE/auth/farmer/register", [
	"name" => "Test Farmer",
	"email" => $farmerEmail,
	"password" => $password,
	"farm_name" => "Test Farm",
	"region" => "Kyiv Region",
]);
assert_ok(
	"POST /auth/farmer/register",
	$r,
	201,
	fn($b) => isset($b["data"]["access_token"]),
);
$state["farmer_token"] = $r["body"]["data"]["access_token"] ?? null;

$r = req("POST", "$BASE/auth/login", [
	"email" => $customerEmail,
	"password" => $password,
]);
assert_ok(
	"POST /auth/login",
	$r,
	200,
	fn($b) => isset($b["data"]["access_token"]),
);
$refreshToken = $r["body"]["data"]["refresh_token"] ?? null;

$r = req("POST", "$BASE/auth/login", [
	"email" => $customerEmail,
	"password" => "wrongpass",
]);
assert_ok("POST /auth/login → 401 bad credentials", $r, 401);

$r = req("POST", "$BASE/auth/refresh", ["refresh_token" => $refreshToken]);
assert_ok(
	"POST /auth/refresh",
	$r,
	200,
	fn($b) => isset($b["data"]["access_token"]),
);

$r = req("POST", "$BASE/auth/forgot-password", ["email" => $customerEmail]);
assert_ok("POST /auth/forgot-password", $r, 200);

$r = req("POST", "$BASE/auth/reset-password", [
	"token" => "invalid",
	"password" => "NewPass123!",
]);
assert_ok("POST /auth/reset-password → 400 invalid token", $r, 400);

$r = req("POST", "$BASE/auth/logout", ["refresh_token" => $refreshToken]);
assert_ok("POST /auth/logout", $r, 200);

// -- Products ------------------------------------------------------------------
section("Products");

$r = req("GET", "$BASE/products");
assert_ok(
	"GET /products",
	$r,
	200,
	fn($b) => isset($b["data"]) && isset($b["meta"]),
);
$state["product_id"] = $r["body"]["data"][0]["id"] ?? null;

$r = req("GET", "$BASE/products?category=vegetables");
assert_ok("GET /products?category=vegetables", $r, 200);

$r = req("GET", "$BASE/products?price_tier=low");
assert_ok("GET /products?price_tier=low", $r, 200);

$r = req("GET", "$BASE/products?sort=price_asc&per_page=3");
assert_ok("GET /products?sort=price_asc&per_page=3", $r, 200);

$r = req("GET", "$BASE/products?search=beet");
assert_ok("GET /products?search=beet", $r, 200);

$r = req("GET", "$BASE/products/featured");
assert_ok("GET /products/featured", $r, 200);

if ($state["product_id"]) {
	$r = req("GET", "$BASE/products/{$state["product_id"]}");
	assert_ok(
		"GET /products/{id}",
		$r,
		200,
		fn($b) => isset($b["data"]["farmer"]),
	);
}

$r = req("GET", "$BASE/products/99999");
assert_ok("GET /products/99999 → 404", $r, 404);

$r = req("GET", "$BASE/categories");
assert_ok("GET /categories", $r, 200, fn($b) => is_array($b["data"]));

// -- Farmers -------------------------------------------------------------------
section("Farmers");

$r = req("GET", "$BASE/farmers");
assert_ok("GET /farmers", $r, 200, fn($b) => isset($b["data"]));
$publicFarmerId = $r["body"]["data"][0]["id"] ?? null;

if ($publicFarmerId) {
	$r = req("GET", "$BASE/farmers/$publicFarmerId");
	assert_ok("GET /farmers/{id}", $r, 200);

	$r = req("GET", "$BASE/farmers/$publicFarmerId/products");
	assert_ok("GET /farmers/{id}/products", $r, 200);
}

$r = req("GET", "$BASE/farmers/99999");
assert_ok("GET /farmers/99999 → 404", $r, 404);

// -- Delivery ------------------------------------------------------------------
section("Delivery");

$r = req("GET", "$BASE/delivery/slots");
assert_ok("GET /delivery/slots", $r, 200, fn($b) => isset($b["data"]["slots"]));
$state["delivery_slot_id"] = $r["body"]["data"]["slots"][0]["id"] ?? null;

$r = req(
	"GET",
	"$BASE/delivery/slots?date=" .
		date("Y-m-d", strtotime("+1 day")) .
		"&city=Kyiv",
);
assert_ok("GET /delivery/slots?date=&city=", $r, 200);

$r = req("GET", "$BASE/delivery/zones");
assert_ok(
	"GET /delivery/zones",
	$r,
	200,
	fn($b) => ($b["data"]["type"] ?? "") === "FeatureCollection",
);

// -- Orders --------------------------------------------------------------------
section("Orders");

$orderBody = [
	"buyer" => [
		"name" => "Test Buyer",
		"phone" => "+380501234567",
		"email" => "buyer@test.ua",
	],
	"delivery_address" => [
		"city" => "Kyiv",
		"street" => "вул. Хрещатик, 1",
		"lat" => 50.4501,
		"lng" => 30.5234,
	],
	"items" => [["product_id" => $state["product_id"] ?? 1, "qty" => 1]],
	"delivery_slot_id" => $state["delivery_slot_id"],
	"note" => "Test order",
];

$r = req("POST", "$BASE/orders", $orderBody);
assert_ok(
	"POST /orders (guest)",
	$r,
	201,
	fn($b) => isset($b["data"]["tracking_token"]),
);
$state["order_id"] = $r["body"]["data"]["order_id"] ?? null;
$state["tracking_token"] = $r["body"]["data"]["tracking_token"] ?? null;

$r = req("POST", "$BASE/orders", $orderBody, $state["customer_token"]);
assert_ok(
	"POST /orders (authenticated)",
	$r,
	201,
	fn($b) => isset($b["data"]["points_earned"]),
);

$r = req("POST", "$BASE/orders", ["buyer" => ["name" => "X"], "items" => []]);
assert_ok("POST /orders → 422 validation", $r, 422);

if ($state["order_id"] && $state["tracking_token"]) {
	$r = req(
		"GET",
		"$BASE/orders/{$state["order_id"]}/tracking?token={$state["tracking_token"]}",
	);
	assert_ok(
		"GET /orders/{id}/tracking?token=",
		$r,
		200,
		fn($b) => isset($b["data"]["timeline"]),
	);
}

if ($state["order_id"]) {
	$r = req("GET", "$BASE/orders/{$state["order_id"]}/tracking");
	assert_ok("GET /orders/{id}/tracking → 400 no token", $r, 400);
}

// -- Customer /me --------------------------------------------------------------
section("Customer — /me");

$r = req("GET", "$BASE/me");
assert_ok("GET /me → 401 no token", $r, 401);

$r = req("GET", "$BASE/me", [], $state["customer_token"]);
assert_ok(
	"GET /me",
	$r,
	200,
	fn($b) => isset($b["data"]["loyalty"]) && isset($b["data"]["address"]),
);

$r = req(
	"PUT",
	"$BASE/me",
	["name" => "Anna Updated", "phone" => "+380991234567"],
	$state["customer_token"],
);
assert_ok(
	"PUT /me",
	$r,
	200,
	fn($b) => ($b["data"]["name"] ?? "") === "Anna Updated",
);

$r = req("GET", "$BASE/me/orders", [], $state["customer_token"]);
assert_ok(
	"GET /me/orders",
	$r,
	200,
	fn($b) => isset($b["data"]) && isset($b["meta"]),
);

$r = req("GET", "$BASE/me/orders?status=pending", [], $state["customer_token"]);
assert_ok("GET /me/orders?status=pending", $r, 200);

$r = req("GET", "$BASE/me/points", [], $state["customer_token"]);
assert_ok(
	"GET /me/points",
	$r,
	200,
	fn($b) => isset($b["data"]["balance"]) && isset($b["data"]["tier"]),
);

$r = req("GET", "$BASE/me/points/history", [], $state["customer_token"]);
assert_ok("GET /me/points/history", $r, 200, fn($b) => isset($b["data"]));

$r = req(
	"POST",
	"$BASE/me/points/redeem",
	["points_to_redeem" => 50, "order_id" => 1],
	$state["customer_token"],
);
assert_ok("POST /me/points/redeem → 422 not multiple of 100", $r, 422);

$r = req(
	"POST",
	"$BASE/me/points/redeem",
	["points_to_redeem" => 99900, "order_id" => 1],
	$state["customer_token"],
);
assert_ok("POST /me/points/redeem → 422 insufficient", $r, 422);

// -- Subscriptions -------------------------------------------------------------
section("Customer — Subscriptions");

$subBody = [
	"items" => [["product_id" => $state["product_id"] ?? 1, "qty" => 2]],
	"schedule" => [
		"frequency" => "weekly",
		"day_of_week" => 2,
		"delivery_slot" => "10:00-14:00",
	],
	"delivery_address" => [
		"city" => "Kyiv",
		"street" => "вул. Хрещатик, 1",
		"lat" => 50.4501,
		"lng" => 30.5234,
	],
];

$r = req("GET", "$BASE/me/subscriptions", [], $state["customer_token"]);
assert_ok("GET /me/subscriptions", $r, 200, fn($b) => isset($b["data"]));

$r = req("POST", "$BASE/me/subscriptions", $subBody, $state["customer_token"]);
assert_ok("POST /me/subscriptions", $r, 201, fn($b) => isset($b["data"]["id"]));
$state["subscription_id"] = $r["body"]["data"]["id"] ?? null;

if ($state["subscription_id"]) {
	$r = req(
		"GET",
		"$BASE/me/subscriptions/{$state["subscription_id"]}",
		[],
		$state["customer_token"],
	);
	assert_ok("GET /me/subscriptions/{id}", $r, 200);

	$r = req(
		"PATCH",
		"$BASE/me/subscriptions/{$state["subscription_id"]}",
		[
			"action" => "pause",
			"pause_until" => date("Y-m-d", strtotime("+14 days")),
		],
		$state["customer_token"],
	);
	assert_ok(
		"PATCH /me/subscriptions/{id} pause",
		$r,
		200,
		fn($b) => ($b["data"]["status"] ?? "") === "paused",
	);

	$r = req(
		"PATCH",
		"$BASE/me/subscriptions/{$state["subscription_id"]}",
		["action" => "resume"],
		$state["customer_token"],
	);
	assert_ok(
		"PATCH /me/subscriptions/{id} resume",
		$r,
		200,
		fn($b) => ($b["data"]["status"] ?? "") === "active",
	);

	$r = req(
		"DELETE",
		"$BASE/me/subscriptions/{$state["subscription_id"]}",
		[],
		$state["customer_token"],
	);
	assert_ok("DELETE /me/subscriptions/{id}", $r, 200);

	$r = req(
		"GET",
		"$BASE/me/subscriptions/99999",
		[],
		$state["customer_token"],
	);
	assert_ok("GET /me/subscriptions/99999 → 404", $r, 404);
}

// -- Farmer Dashboard ----------------------------------------------------------
section("Farmer Dashboard");

$r = req("GET", "$BASE/farmer/profile");
assert_ok("GET /farmer/profile → 401 no token", $r, 401);

$r = req("GET", "$BASE/farmer/profile", [], $state["farmer_token"]);
assert_ok(
	"GET /farmer/profile",
	$r,
	200,
	fn($b) => isset($b["data"]["farm_name"]),
);

$r = req(
	"PUT",
	"$BASE/farmer/profile",
	["farm_name" => "Updated Farm Name", "region" => "Lviv Region"],
	$state["farmer_token"],
);
assert_ok("PUT /farmer/profile", $r, 200);

$r = req("GET", "$BASE/farmer/products", [], $state["farmer_token"]);
assert_ok("GET /farmer/products", $r, 200, fn($b) => isset($b["data"]));

$r = req(
	"POST",
	"$BASE/farmer/products",
	[
		"name" => "Test Tomatoes",
		"price" => 3.5,
		"category_id" => 1,
		"stock_qty" => 50,
		"unit" => "kg",
		"description" => "Fresh test tomatoes",
	],
	$state["farmer_token"],
);
assert_ok("POST /farmer/products", $r, 201, fn($b) => isset($b["data"]["id"]));
$state["farmer_product_id"] = $r["body"]["data"]["id"] ?? null;

$r = req(
	"POST",
	"$BASE/farmer/products",
	["name" => "X"],
	$state["farmer_token"],
);
assert_ok("POST /farmer/products → 422 validation", $r, 422);

if ($state["farmer_product_id"]) {
	$r = req(
		"PUT",
		"$BASE/farmer/products/{$state["farmer_product_id"]}",
		["price" => 4.0, "stock_qty" => 40],
		$state["farmer_token"],
	);
	assert_ok(
		"PUT /farmer/products/{id}",
		$r,
		200,
		fn($b) => (float) ($b["data"]["price"] ?? 0) === 4.0,
	);

	$r = req(
		"DELETE",
		"$BASE/farmer/products/{$state["farmer_product_id"]}",
		[],
		$state["farmer_token"],
	);
	assert_ok("DELETE /farmer/products/{id}", $r, 200);
}

$r = req(
	"PUT",
	"$BASE/farmer/products/99999",
	["price" => 1.0],
	$state["farmer_token"],
);
assert_ok("PUT /farmer/products/99999 → 404", $r, 404);

$r = req("GET", "$BASE/farmer/orders", [], $state["farmer_token"]);
assert_ok("GET /farmer/orders", $r, 200, fn($b) => isset($b["data"]));

$r = req("GET", "$BASE/farmer/stats", [], $state["farmer_token"]);
assert_ok(
	"GET /farmer/stats",
	$r,
	200,
	fn($b) => isset($b["data"]["total_revenue"]),
);

$r = req("GET", "$BASE/farmer/profile", [], $state["customer_token"]);
assert_ok("GET /farmer/profile → 403 wrong role", $r, 403);

// -- Auth guard edge cases -----------------------------------------------------
section("Security");

$r = req("GET", "$BASE/me", [], "invalid.token.here");
assert_ok("Invalid JWT → 401", $r, 401);

$r = req("GET", "$BASE/me/orders", [], null);
assert_ok("No token → 401", $r, 401);

$r = req("DELETE", "$BASE/me", [], $state["customer_token"]);
assert_ok("DELETE /me (soft delete)", $r, 200);

// --- Summary -----------------------------------------------------------------

$total = $passed + $failed;
echo "\n" . color("  " . str_repeat("-", 50), "90") . "\n";
echo color("  Results: ", "1");
echo color("$passed passed", "32");
echo color(" / ", "90");
echo color("$failed failed", $failed > 0 ? "31" : "32");
echo color(" / $total total", "90") . "\n";

if (!empty($errors)) {
	echo "\n" . color("  Failed tests:", "31;1") . "\n";
	foreach ($errors as $e) {
		echo color("  ✗ {$e["name"]}", "31") . "\n";
		echo color("    {$e["reason"]}", "90") . "\n";
		if (!empty($e["body"])) {
			$preview = json_encode($e["body"], JSON_UNESCAPED_UNICODE);
			if (strlen($preview) > 120) {
				$preview = substr($preview, 0, 120) . "...";
			}
			echo color("    $preview", "90") . "\n";
		}
	}
}

echo "\n";
exit($failed > 0 ? 1 : 0);


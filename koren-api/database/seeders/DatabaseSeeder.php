<?php

use Vine\Database\Connection;

return function() {
    $db = Connection::getInstance();

    $db->statement("TRUNCATE TABLE products, farmer_profiles, users, categories, delivery_slots RESTART IDENTITY CASCADE");

    $db->query("INSERT INTO categories (slug, name) VALUES (:s, :n)", [':s' => 'vegetables', ':n' => 'Vegetables']);
    $db->query("INSERT INTO categories (slug, name) VALUES (:s, :n)", [':s' => 'fruits',     ':n' => 'Fruits']);
    $db->query("INSERT INTO categories (slug, name) VALUES (:s, :n)", [':s' => 'eggs-dairy', ':n' => 'Eggs & Dairy']);
    $db->query("INSERT INTO categories (slug, name) VALUES (:s, :n)", [':s' => 'berries',    ':n' => 'Berries']);

    echo "  Seeded categories\n";

    $farmers = [
        ['Olivia Reynolds', 'olivia@farm.ua', 'Lviv Region',        'Harrison Free-Range',      12, "The soil is a living creature..."],
        ['Ethan Carter',    'ethan@farm.ua',  'Poltava Region',     'Carter Heritage Tomatoes', 9,  "Biohimist dad, compare only..."],
        ['Noah Harrison',   'noah@farm.ua',   'Zhytomyr Region',    'Harrison Free-Range Farm', 6,  "Four hectares of open pasture."],
        ['Sophia Mitchell', 'sophia@farm.ua', 'Ivano-Frankivsk',    'Mitchell Berry Farm',      8,  "I grow for the person who actually wants to taste something."],
        ['James Whitaker',  'james@farm.ua',  'Vinnytsia Region',   'Whitaker Orchard',         10, "My grandfather planted these trees."],
    ];

    $farmerIds = [];
    foreach ($farmers as $f) {
        $db->query(
            "INSERT INTO users (name, email, password_hash, role, is_verified) VALUES (:n, :e, :p, 'farmer', TRUE)",
            [':n' => $f[0], ':e' => $f[1], ':p' => password_hash('password', PASSWORD_BCRYPT)]
        );
        $userId = $db->pdo()->lastInsertId();
        $db->query(
            "INSERT INTO farmer_profiles (user_id, farm_name, region, bio_short, years_exp) VALUES (:u, :f, :r, :b, :y)",
            [':u' => $userId, ':f' => $f[3], ':r' => $f[2], ':b' => $f[5], ':y' => $f[4]]
        );
        $farmerIds[] = $userId;
    }

    echo "  Seeded " . count($farmerIds) . " farmers\n";

    $products = [
        [$farmerIds[1], 1, 'Organic Beets',      'organic-beets',      2.50, 'kg',    48,  false],
        [$farmerIds[0], 1, 'Fresh Lettuce',       'fresh-lettuce',      1.20, 'kg',    30,  false],
        [$farmerIds[4], 2, 'Green Apples',        'green-apples',       2.20, 'kg',    60,  false],
        [$farmerIds[2], 3, 'Brown Eggs',          'brown-eggs',         4.50, 'dozen', 120, true],
        [$farmerIds[3], 4, 'Fresh Strawberries',  'fresh-strawberries', 3.80, 'basket',25,  false],
        [$farmerIds[1], 1, 'Broccoli',            'broccoli',           2.70, 'kg',    20,  false],
    ];

    foreach ($products as $p) {
        $db->query(
            "INSERT INTO products (farmer_id, category_id, name, slug, price, unit, stock_qty, is_featured, is_active)
             VALUES (:fi, :ci, :n, :s, :p, :u, :q, :f, TRUE)",
            [':fi' => $p[0], ':ci' => $p[1], ':n' => $p[2], ':s' => $p[3], ':p' => $p[4], ':u' => $p[5], ':q' => $p[6], ':f' => $p[7] ? 'TRUE' : 'FALSE']
        );
    }

    echo "  Seeded " . count($products) . " products\n";

    $slots = [
        [date('Y-m-d', strtotime('+1 day')), 'Kyiv', '09:00-12:00', 10, true],
        [date('Y-m-d', strtotime('+1 day')), 'Kyiv', '13:00-17:00', 10, false],
        [date('Y-m-d', strtotime('+1 day')), 'Kyiv', '18:00-21:00', 8,  false],
        [date('Y-m-d', strtotime('+2 days')), 'Kyiv', '09:00-12:00', 10, true],
        [date('Y-m-d', strtotime('+2 days')), 'Kyiv', '13:00-17:00', 10, false],
    ];

    foreach ($slots as $s) {
        $db->query(
            "INSERT INTO delivery_slots (date, city, time_range, capacity_total, is_eco) VALUES (:d, :c, :t, :cap, :e)",
            [':d' => $s[0], ':c' => $s[1], ':t' => $s[2], ':cap' => $s[3], ':e' => $s[4] ? 'TRUE' : 'FALSE']
        );
    }

    echo "  Seeded delivery slots\n";
};

<?php

return [
    'up' => function($db) {
        $db->statement("
            CREATE TABLE IF NOT EXISTS users (
                id              SERIAL PRIMARY KEY,
                name            VARCHAR(100) NOT NULL,
                email           VARCHAR(150) UNIQUE NOT NULL,
                phone           VARCHAR(20),
                password_hash   VARCHAR(255) NOT NULL,
                role            VARCHAR(20)  DEFAULT 'customer',
                avatar_url      VARCHAR(255),
                address_city    VARCHAR(100),
                address_street  TEXT,
                address_lat     DECIMAL(10,7),
                address_lng     DECIMAL(10,7),
                is_verified     BOOLEAN DEFAULT FALSE,
                created_at      TIMESTAMPTZ DEFAULT NOW()
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id          SERIAL PRIMARY KEY,
                user_id     INT REFERENCES users(id) ON DELETE CASCADE,
                token_hash  VARCHAR(255) UNIQUE NOT NULL,
                expires_at  TIMESTAMPTZ NOT NULL,
                created_at  TIMESTAMPTZ DEFAULT NOW()
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id          SERIAL PRIMARY KEY,
                email       VARCHAR(150) NOT NULL,
                token_hash  VARCHAR(255) UNIQUE NOT NULL,
                expires_at  TIMESTAMPTZ NOT NULL,
                created_at  TIMESTAMPTZ DEFAULT NOW()
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS farmer_profiles (
                user_id         INT PRIMARY KEY REFERENCES users(id),
                farm_name       VARCHAR(150),
                region          VARCHAR(100),
                bio             TEXT,
                bio_short       VARCHAR(280),
                years_exp       INT,
                farm_types      TEXT[],
                rating          DECIMAL(3,2) DEFAULT 0,
                reviews_count   INT DEFAULT 0
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS categories (
                id      SERIAL PRIMARY KEY,
                slug    VARCHAR(50) UNIQUE NOT NULL,
                name    VARCHAR(100) NOT NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS products (
                id              SERIAL PRIMARY KEY,
                farmer_id       INT REFERENCES users(id),
                category_id     INT REFERENCES categories(id),
                name            VARCHAR(150) NOT NULL,
                slug            VARCHAR(180) UNIQUE,
                description     TEXT,
                price           DECIMAL(8,2) NOT NULL,
                unit            VARCHAR(20) DEFAULT 'kg',
                stock_qty       INT DEFAULT 0,
                image_url       VARCHAR(255),
                tags            TEXT[],
                is_featured     BOOLEAN DEFAULT FALSE,
                is_active       BOOLEAN DEFAULT TRUE,
                harvested_at    DATE,
                created_at      TIMESTAMPTZ DEFAULT NOW()
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS loyalty_accounts (
                id              SERIAL PRIMARY KEY,
                user_id         INT UNIQUE REFERENCES users(id),
                balance         INT DEFAULT 0,
                total_earned    INT DEFAULT 0,
                tier            VARCHAR(20) DEFAULT 'sprout'
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS loyalty_transactions (
                id          SERIAL PRIMARY KEY,
                user_id     INT REFERENCES users(id),
                order_id    INT,
                type        VARCHAR(20) NOT NULL,
                amount      INT NOT NULL,
                description TEXT,
                created_at  TIMESTAMPTZ DEFAULT NOW()
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS delivery_slots (
                id              SERIAL PRIMARY KEY,
                date            DATE NOT NULL,
                city            VARCHAR(100) NOT NULL,
                time_range      VARCHAR(20) NOT NULL,
                capacity_total  INT DEFAULT 10,
                capacity_used   INT DEFAULT 0,
                price           DECIMAL(6,2) DEFAULT 0,
                is_eco          BOOLEAN DEFAULT FALSE
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS orders (
                id                  SERIAL PRIMARY KEY,
                user_id             INT REFERENCES users(id),
                buyer_name          VARCHAR(100),
                buyer_phone         VARCHAR(20),
                buyer_email         VARCHAR(150),
                delivery_slot_id    INT REFERENCES delivery_slots(id),
                delivery_address    TEXT,
                delivery_lat        DECIMAL(10,7),
                delivery_lng        DECIMAL(10,7),
                subtotal            DECIMAL(10,2),
                discount            DECIMAL(10,2) DEFAULT 0,
                total               DECIMAL(10,2),
                status              VARCHAR(30) DEFAULT 'pending',
                tracking_token      VARCHAR(64) UNIQUE,
                redeem_points       INT DEFAULT 0,
                subscription_id     INT,
                note                TEXT,
                courier_lat         DECIMAL(10,7),
                courier_lng         DECIMAL(10,7),
                courier_updated_at  TIMESTAMPTZ,
                created_at          TIMESTAMPTZ DEFAULT NOW()
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS order_items (
                id          SERIAL PRIMARY KEY,
                order_id    INT REFERENCES orders(id),
                product_id  INT REFERENCES products(id),
                qty         INT NOT NULL,
                unit_price  DECIMAL(8,2) NOT NULL
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS subscriptions (
                id                  SERIAL PRIMARY KEY,
                user_id             INT REFERENCES users(id),
                status              VARCHAR(20) DEFAULT 'active',
                frequency           VARCHAR(20) NOT NULL,
                day_of_week         INT,
                day_of_month        INT,
                delivery_slot       VARCHAR(20),
                delivery_address    TEXT,
                delivery_lat        DECIMAL(10,7),
                delivery_lng        DECIMAL(10,7),
                next_delivery_at    TIMESTAMPTZ,
                pause_until         DATE,
                note                TEXT,
                created_at          TIMESTAMPTZ DEFAULT NOW()
            )
        ");

        $db->statement("
            CREATE TABLE IF NOT EXISTS subscription_items (
                id                  SERIAL PRIMARY KEY,
                subscription_id     INT REFERENCES subscriptions(id),
                product_id          INT REFERENCES products(id),
                qty                 INT NOT NULL
            )
        ");

        echo "  Created all tables\n";
    },

    'down' => function($db) {
        $tables = [
            'subscription_items', 'subscriptions', 'order_items', 'orders',
            'delivery_slots', 'loyalty_transactions', 'loyalty_accounts',
            'products', 'categories', 'farmer_profiles',
            'password_reset_tokens', 'refresh_tokens', 'users',
        ];
        foreach ($tables as $table) {
            $db->statement("DROP TABLE IF EXISTS $table CASCADE");
        }
        echo "  Dropped all tables\n";
    },
];

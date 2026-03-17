<?php

return [
    'up' => function($db) {
        $db->statement("ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(100) UNIQUE");
        $db->statement("ALTER TABLE users ADD COLUMN IF NOT EXISTS apple_id  VARCHAR(100) UNIQUE");
        $db->statement("ALTER TABLE users ALTER COLUMN password_hash DROP NOT NULL");
        $db->statement("ALTER TABLE users ALTER COLUMN email DROP NOT NULL");

        $db->statement("
            CREATE TABLE IF NOT EXISTS payments (
                id                          SERIAL PRIMARY KEY,
                order_id                    INT REFERENCES orders(id),
                user_id                     INT REFERENCES users(id),
                stripe_payment_intent_id    VARCHAR(100) UNIQUE,
                stripe_client_secret        VARCHAR(255),
                amount                      DECIMAL(10,2) NOT NULL,
                currency                    VARCHAR(3) DEFAULT 'eur',
                status                      VARCHAR(30) DEFAULT 'pending',
                created_at                  TIMESTAMPTZ DEFAULT NOW()
            )
        ");

        echo "  OAuth columns and payments table added\n";
    },

    'down' => function($db) {
        $db->statement("DROP TABLE IF EXISTS payments CASCADE");
        $db->statement("ALTER TABLE users DROP COLUMN IF EXISTS google_id");
        $db->statement("ALTER TABLE users DROP COLUMN IF EXISTS apple_id");
        $db->statement("ALTER TABLE users ALTER COLUMN password_hash SET NOT NULL");
        $db->statement("ALTER TABLE users ALTER COLUMN email SET NOT NULL");
        echo "  Reverted OAuth columns and payments table\n";
    },
];

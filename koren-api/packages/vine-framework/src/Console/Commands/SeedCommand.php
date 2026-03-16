<?php

namespace Vine\Console\Commands;

class SeedCommand
{
    public function handle(array $args): void
    {
        $specific = $args[0] ?? null;
        $seedersPath = getcwd() . '/database/seeders';

        if (!is_dir($seedersPath)) {
            echo "No seeders directory.\n";
            return;
        }

        echo "\nSeeding database...\n";

        if ($specific) {
            $file = $seedersPath . '/' . $specific . '.php';
            if (!file_exists($file)) {
                echo "Seeder not found: $specific\n";
                return;
            }
            $this->runSeeder($file, $specific);
            return;
        }

        $mainSeeder = $seedersPath . '/DatabaseSeeder.php';
        if (file_exists($mainSeeder)) {
            $this->runSeeder($mainSeeder, 'DatabaseSeeder');
        } else {
            $files = glob($seedersPath . '/*.php');
            foreach ($files as $file) {
                $this->runSeeder($file, basename($file, '.php'));
            }
        }

        echo "\nDone.\n";
    }

    private function runSeeder(string $file, string $name): void
    {
        $seeder = require $file;
        if (is_callable($seeder)) {
            $seeder();
            echo "  Seeded: $name\n";
        }
    }
}

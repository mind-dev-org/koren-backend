<?php

namespace Vine\Console\Commands;

use Vine\Database\Connection;

class MigrateCommand
{
	public function handle(array $args): void
	{
		$action = $args[0] ?? "up";

		if ($action === "rollback") {
			$this->rollback();
			return;
		}

		if ($action === "status") {
			$this->status();
			return;
		}

		$this->up();
	}

	private function up(): void
	{
		echo "\nRunning migrations...\n";

		$db = Connection::getInstance();

		$db->statement("CREATE TABLE IF NOT EXISTS vine_migrations (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) UNIQUE NOT NULL,
            batch INT DEFAULT 1,
            executed_at TIMESTAMPTZ DEFAULT NOW()
        )");

		$migrationsPath = getcwd() . "/database/migrations";
		if (!is_dir($migrationsPath)) {
			echo "No migrations directory found.\n";
			return;
		}

		$files = glob($migrationsPath . "/*.php");
		sort($files);

		$executed = $db->select("SELECT migration FROM vine_migrations");
		$executedNames = array_column($executed, "migration");

		$batch = $this->getNextBatch($db);
		$count = 0;

		foreach ($files as $file) {
			$name = basename($file, ".php");
			if (in_array($name, $executedNames)) {
				continue;
			}

			$migration = require $file;
			if (is_callable($migration["up"] ?? null)) {
				$migration["up"]($db);
				// $db->statement(
				//     "INSERT INTO vine_migrations (migration, batch) VALUES (:m, :b)",
				// );
				$db->query(
					"INSERT INTO vine_migrations (migration, batch) VALUES (:m, :b)",
					[":m" => $name, ":b" => $batch],
				);
				echo "  Migrated: $name\n";
				$count++;
			}
		}

		echo $count > 0
			? "\nDone. $count migration(s) ran.\n"
			: "\nNothing to migrate.\n";
	}

	private function rollback(): void
	{
		echo "\nRolling back last batch...\n";

		$db = Connection::getInstance();
		$lastBatch = $db->selectOne(
			"SELECT MAX(batch) as batch FROM vine_migrations",
		);
		$batch = $lastBatch["batch"] ?? null;

		if (!$batch) {
			echo "Nothing to rollback.\n";
			return;
		}

		$migrations = $db->select(
			"SELECT migration FROM vine_migrations WHERE batch = :b ORDER BY id DESC",
			[":b" => $batch],
		);

		$migrationsPath = getcwd() . "/database/migrations";

		foreach ($migrations as $row) {
			$file = $migrationsPath . "/" . $row["migration"] . ".php";
			if (file_exists($file)) {
				$migration = require $file;
				if (is_callable($migration["down"] ?? null)) {
					$migration["down"]($db);
					echo "  Rolled back: {$row["migration"]}\n";
				}
			}
			$db->query("DELETE FROM vine_migrations WHERE migration = :m", [
				":m" => $row["migration"],
			]);
		}

		echo "\nDone.\n";
	}

	private function status(): void
	{
		$db = Connection::getInstance();
		$rows = $db->select(
			"SELECT migration, batch, executed_at FROM vine_migrations ORDER BY id",
		);
		echo "\nMigration Status:\n\n";
		foreach ($rows as $row) {
			echo "  [batch {$row["batch"]}] {$row["migration"]} ({$row["executed_at"]})\n";
		}
		echo "\n";
	}

	private function getNextBatch(Connection $db): int
	{
		$row = $db->selectOne(
			"SELECT MAX(batch) as batch FROM vine_migrations",
		);
		return ((int) ($row["batch"] ?? 0)) + 1;
	}
}

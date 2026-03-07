<?php

namespace Vine\Console;

class Cli
{
    private array $commands = [];

    public function register(string $name, string $commandClass): void
    {
        $this->commands[$name] = $commandClass;
    }

    public function run(array $argv): void
    {
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);

        if ($command === 'help' || !isset($this->commands[$command])) {
            $this->showHelp();
            return;
        }

        $instance = new $this->commands[$command]();
        $instance->handle($args);
    }

    private function showHelp(): void
    {
        echo "\nVine CLI\n\n";
        echo "Available commands:\n";
        foreach ($this->commands as $name => $class) {
            echo "  $name\n";
        }
        echo "\n";
    }
}

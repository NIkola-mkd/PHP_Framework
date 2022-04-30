<?php

namespace app\core;

use PDO;

class Database
{
    public PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = $config['dsn'] ?? '';
        $user = $config['user'] ?? '';
        $password = $config['password'] ?? '';

        $this->pdo = new PDO($dsn, $user, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function applyMigrations()
    {
        $this->createMigrationsTable();
        $appliedMigrations =  $this->getAppliedMigrations();

        $newMigrations = [];
        $files = scandir(Application::$rootDIR . '/migrations');

        $toApplyMigrations =  array_diff($files, $appliedMigrations);

        foreach ($toApplyMigrations as $migration) {

            if ($migration === '.' || $migration === '..') {

                continue;
            }

            require_once Application::$rootDIR . '/migrations/' . $migration;

            $className = pathinfo($migration, PATHINFO_FILENAME);

            $instance = new $className;
            $this->log('Migrating: ' . $migration);
            $instance->up();
            $this->log($migration . " migrated ");

            $newMigrations[] = $migration;
        }

        if (!empty($newMigrations)) {
            $this->saveMigrations($newMigrations);
        } else {
            $this->log('Nothing to migrate');
        }
    }

    public function createMigrationsTable()
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP       
        );');
    }

    public function getAppliedMigrations()
    {
        $statement = $this->pdo->prepare('SELECT migration FROM migrations');
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    public function saveMigrations(array $migrations)
    {
        $str = implode(",", array_map(fn ($m) => "('$m')", $migrations));
        $statement = $this->pdo->prepare("INSERT INTO migrations (migration) VALUE ($str)");

        $statement->execute();
    }

    protected function log($message)
    {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    }
}
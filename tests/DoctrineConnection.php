<?php

declare(strict_types=1);

namespace Crell\QueueTest;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

trait DoctrineConnection
{
    protected Connection $conn;

    protected function getConnection(): Connection
    {
        return $this->conn ??= $this->createConnection();
    }

    protected function createConnection(): Connection
    {
        $connectionParams = [
            //'dbname' => 'rekodi',
            'user' => 'root',
            'password' => 'test',
            'host' => 'db',
            'driver' => 'pdo_mysql',
            // Emulated prepared statements on PHP 8.0 don't
            // return values in the correct type, so disable them for now.
            'driverOptions' => [
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ];
        return DriverManager::getConnection($connectionParams);
    }

    public function resetDatabase(string $name): void
    {
        $conn = $this->getConnection();

        $sm = $conn->createSchemaManager();
        $sm->dropDatabase($name);
        $sm->createDatabase($name);

        // This line may be MySQL-specific.
        $conn->executeQuery("USE " . $name);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Doctrine\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class RetryableTransactionDeadlockHelper
{
    private Connection $connection;
    private string $id1;
    private string $id2;

    public function __construct(array $params, string $id1, string $id2)
    {
        $this->connection = DriverManager::getConnection($params);
        $this->id1 = $id1;
        $this->id2 = $id2;
    }

    public function execute(): void
    {
        $this->connection->beginTransaction();

        // Lock row 2
        $this->connection->executeStatement(
            'SELECT * FROM tag WHERE id = UNHEX(:id) FOR UPDATE',
            ['id' => $this->id2]
        );

        // Wait for a short time to ensure the main process has locked row 1
        usleep(50000); // 50ms

        // Try to lock row 1 to complete the deadlock
        $this->connection->executeStatement(
            'SELECT * FROM tag WHERE id = UNHEX(:id) FOR UPDATE',
            ['id' => $this->id1]
        );

        $this->connection->rollBack();
    }
}

<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Query;

use Doctrine\DBAL\Connection;

class InsertQuery extends ApiQuery
{
    /**
     * @var string
     */
    private $tableName;
    /**
     * @var array
     */
    private $payload;

    public function __construct(string $tableName, array $payload)
    {
        $this->tableName = $tableName;
        $this->payload = $payload;
    }

    public function isExecutable(): bool
    {
        return (bool) count($this->payload);
    }

    public function execute(Connection $connection): int
    {
        return $connection->insert($this->tableName, $this->payload);
    }
}

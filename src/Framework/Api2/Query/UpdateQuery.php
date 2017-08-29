<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Query;


use Doctrine\DBAL\Connection;

class UpdateQuery extends ApiQuery
{
    /**
     * @var string
     */
    private $tableName;
    /**
     * @var array
     */
    private $pkData;
    /**
     * @var array
     */
    private $payload;

    public function __construct(string $tableName, array $pkData, array $payload)
    {
        $this->tableName = $tableName;
        $this->pkData = $pkData;
        $this->payload = $payload;
    }

    public function isExecutable(): bool
    {
        return (bool) count($this->payload);
    }


    public function execute(Connection $connection): int
    {
        return $connection->update($this->tableName, $this->payload, $this->pkData);
    }
}
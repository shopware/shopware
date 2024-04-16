<?php

namespace Shopware\Core\Framework\Api\HealthCheck\Service\Check;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Api\HealthCheck\Model\Result;
use Shopware\Core\Framework\Api\HealthCheck\Model\Status;
use Shopware\Core\Framework\Api\HealthCheck\Service\Check;

class Database implements Check
{

    public function __construct(private readonly Connection $connection)
    {
    }

    public function run(): Result
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
            return new Result('Database', Status::Healthy);
        } catch (Exception $e) {
            return new Result('Database', Status::Error);
        }
    }

    public function priority(): int
    {
       return 0;
    }
}

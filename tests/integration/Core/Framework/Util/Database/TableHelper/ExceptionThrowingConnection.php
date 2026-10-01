<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Util\Database\TableHelper;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * @internal
 */
class ExceptionThrowingConnection implements DriverConnection
{
    public function __construct(private readonly DriverConnection $innerConnection)
    {
    }

    public function prepare(string $sql): Statement
    {
        if (str_contains($sql, 'SELECT TABLE_NAME')) {
            throw new \RuntimeException('test');
        }

        return $this->innerConnection->prepare($sql);
    }

    public function query(string $sql): Result
    {
        return $this->innerConnection->query($sql);
    }

    public function quote(string $value): string
    {
        return $this->innerConnection->quote($value);
    }

    public function exec(string $sql): int|string
    {
        return $this->innerConnection->exec($sql);
    }

    public function lastInsertId(): int|string
    {
        return $this->innerConnection->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->innerConnection->beginTransaction();
    }

    public function commit(): void
    {
        $this->innerConnection->commit();
    }

    public function rollBack(): void
    {
        $this->innerConnection->rollBack();
    }

    public function getNativeConnection()
    {
        return $this->innerConnection;
    }

    public function getServerVersion(): string
    {
        return $this->innerConnection->getServerVersion();
    }
}

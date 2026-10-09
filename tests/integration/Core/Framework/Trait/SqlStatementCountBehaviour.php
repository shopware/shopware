<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Trait;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
trait SqlStatementCountBehaviour
{
    /**
     * MySQL's `Questions` session counter increments once per statement sent on
     * this connection, so it measures DBAL round trips rather than rows. The
     * closing read is itself a statement and is subtracted again.
     */
    public function countSqlStatements(callable $callback): int
    {
        $connection = static::getContainer()->get(Connection::class);

        $before = $this->readQuestions($connection);

        $callback();

        $after = $this->readQuestions($connection);

        return $after - $before - 1;
    }

    private function readQuestions(Connection $connection): int
    {
        $row = $connection->fetchAssociative(<<<'SQL'
            SHOW SESSION STATUS LIKE 'Questions'
            SQL);

        \assert(\is_array($row));

        return (int) $row['Value'];
    }
}

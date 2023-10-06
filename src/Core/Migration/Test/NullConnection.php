<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class NullConnection extends Connection
{
    final public const EXCEPTION_MESSAGE = 'Write operations are not supported when using executeQuery.';

    private Connection $originalConnection;

    /**
     * @phpstan-ignore-next-line DBAL Connection uses psalm-consistent-constructor annotation,
     * therefore deriving classes should not change the constructor args, as we are in tests we ignore the error
     */
    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $matches = preg_match_all('/^\s*(UPDATE|ALTER|BACKUP|CREATE|DELETE|DROP|EXEC|INSERT|TRUNCATE)/i', $sql);

        if ($matches) {
            throw new \RuntimeException(self::EXCEPTION_MESSAGE);
        }

        return $this->originalConnection->executeQuery($sql, $params, $types, $qcp);
    }

    public function prepare(string $statement): Statement
    {
        return $this->originalConnection->prepare($statement);
    }

    public function executeUpdate(string $sql, array $params = [], array $types = []): int
    {
        return 0;
    }

    public function executeStatement($sql, array $params = [], array $types = [])
    {
        return 0;
    }

    public function exec(string $statement): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql): Result
    {
        return $this->originalConnection->executeQuery($sql);
    }

    public function insert($table, array $data, array $types = [])
    {
        return 0;
    }

    public function update($table, array $data, array $criteria, array $types = [])
    {
        return 0;
    }

    public function delete($table, array $criteria, array $types = [])
    {
        return $this->originalConnection->delete($table, $criteria, $types);
    }

    public function setOriginalConnection(Connection $originalConnection): void
    {
        $this->originalConnection = $originalConnection;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ForwardCompatibility\DriverResultStatement;
use Doctrine\DBAL\ForwardCompatibility\DriverStatement;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Shopware\Core\Profiling\Doctrine\DebugStack;

/**
 * @package core
 *
 * @internal
 */
class NullConnection extends Connection
{
    public const EXCEPTION_MESSAGE = 'Write operations are not supported when using executeQuery.';

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
     *
     * @return DriverResultStatement<mixed>|DriverStatement<mixed>|Result
     */
    public function executeQuery($sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $matches = preg_match_all(DebugStack::$writeSqlRegex, $sql);

        if ($matches) {
            throw new \RuntimeException(self::EXCEPTION_MESSAGE);
        }

        return $this->originalConnection->executeQuery($sql, $params, $types, $qcp);
    }

    public function prepare($statement): Statement
    {
        return $this->originalConnection->prepare($statement);
    }

    public function executeUpdate($sql, array $params = [], array $types = []): int
    {
        return 0;
    }

    public function executeStatement($sql, array $params = [], array $types = [])
    {
        return 0;
    }

    public function exec($statement): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * @return DriverResultStatement<mixed>|DriverStatement<mixed>|Result
     */
    public function query()
    {
        return $this->originalConnection->executeQuery(...\func_get_args());
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

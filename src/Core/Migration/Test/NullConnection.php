<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Shopware\Core\Profiling\Doctrine\DebugStack;

class NullConnection extends Connection
{
    public const EXCEPTION_MESSAGE = 'Write operations are not supported when using executeQuery.';

    /**
     * @var Connection
     */
    private $originalConnection;

    public function __construct()
    {
    }

    public function executeQuery($query, array $params = [], $types = [], ?QueryCacheProfile $qcp = null)
    {
        $matches = preg_match_all(DebugStack::$writeSqlRegex, $query);

        if ($matches) {
            throw new \RuntimeException(self::EXCEPTION_MESSAGE);
        }

        return $this->originalConnection->executeQuery($query, $params, $types, $qcp);
    }

    public function prepare($statement)
    {
        return $this->originalConnection->prepare($statement);
    }

    public function executeUpdate($query, array $params = [], array $types = [])
    {
        return 0;
    }

    public function exec($statement)
    {
        return 0;
    }

    public function query()
    {
        return $this->originalConnection->query(...\func_get_args());
    }

    public function update($tableExpression, array $data, array $identifier, array $types = [])
    {
        return 0;
    }

    public function delete($tableExpression, array $identifier, array $types = [])
    {
        return $this->originalConnection->delete($tableExpression, $identifier, $types);
    }

    public function setOriginalConnection($originalConnection): void
    {
        $this->originalConnection = $originalConnection;
    }

    public function getWrappedConnection()
    {
        return $this->originalConnection;
    }
}

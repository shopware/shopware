<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Result;
use Shopware\Core\Framework\Log\Package;

/**
 * Doctrine routes executeQuery() to the replica whenever the connection is not already on the primary, no matter
 * what the statement does. A write issued that way lands on the replica and drifts the two silently when the
 * replica is not read-only. This wrapper inspects the statement and moves to the primary first, so a write
 * through executeQuery() behaves like one through executeStatement().
 *
 * @phpstan-import-type WrapperParameterTypeArray from Connection
 *
 * @internal
 */
#[Package('framework')]
class WriteAwareReplicaConnection extends PrimaryReadReplicaConnection
{
    /**
     * Leading comments and opening parentheses are skipped before the first keyword is inspected.
     */
    private const LEADING_NOISE = '(?:\s|\/\*.*?\*\/|--[^\n]*(?:\n|$)|#[^\n]*(?:\n|$)|\()*';

    private const WRITE_KEYWORDS = 'INSERT|UPDATE|DELETE|REPLACE|TRUNCATE|ALTER|CREATE|DROP|RENAME|LOAD|EXEC|BACKUP';

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @phpstan-param WrapperParameterTypeArray $types
     */
    public function executeQuery(string $sql, array $params = [], array $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        if (!$this->isConnectedToPrimary() && self::isWriteStatement($sql)) {
            $this->ensureConnectedToPrimary();
        }

        return parent::executeQuery($sql, $params, $types, $qcp);
    }

    public static function isWriteStatement(string $sql): bool
    {
        if (preg_match('/^' . self::LEADING_NOISE . '(?:' . self::WRITE_KEYWORDS . ')\b/is', $sql) === 1) {
            return true;
        }

        // a common table expression can front a data change
        if (preg_match('/^' . self::LEADING_NOISE . 'WITH\b/is', $sql) === 1) {
            return preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE)\b/i', $sql) === 1;
        }

        // locking reads only make sense against the primary
        return preg_match('/^' . self::LEADING_NOISE . 'SELECT\b/is', $sql) === 1
            && preg_match('/\b(?:FOR\s+(?:UPDATE|SHARE)|LOCK\s+IN\s+SHARE\s+MODE)\b/i', $sql) === 1;
    }
}

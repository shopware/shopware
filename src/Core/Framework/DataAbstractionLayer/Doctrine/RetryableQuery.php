<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception\RetryableException;

class RetryableQuery
{
    /**
     * @var Connection|null
     */
    private $connection;

    /**
     * @var Statement
     */
    private $query;

    /**
     * @param Connection $param1
     * @param Statement  $param2
     */
    public function __construct($param1, $param2 = null)
    {
        if ($param1 instanceof Statement && $param2 === null) {
            @trigger_error(
                'Use constructor arguments (Doctrine\DBAL\Connection $connection, Doctrine\DBAL\Driver\Statement $query) instead.',
                \E_USER_DEPRECATED
            );
            $this->query = $param1;
        } elseif ($param1 instanceof Connection && $param2 instanceof Statement) {
            $this->connection = $param1;
            $this->query = $param2;
        } else {
            throw new \InvalidArgumentException(
                'Constructor arguments must be of type (Doctrine\DBAL\Connection $connection, Doctrine\DBAL\Driver\Statement $query).'
            );
        }
    }

    public function execute(?array $params = null): bool
    {
        return self::retry($this->connection, function () use ($params) {
            return $this->query->execute($params);
        }, 1);
    }

    /**
     * @param Connection $param1
     * @param \Closure   $param2
     */
    public static function retryable($param1, $param2 = null)
    {
        if ($param1 instanceof \Closure && $param2 === null) {
            @trigger_error(
                'Use arguments (Doctrine\DBAL\Connection $connection, \Closure $closure) instead.',
                \E_USER_DEPRECATED
            );

            return self::retry(null, $param1, 0);
        }

        if ($param1 instanceof Connection && $param2 instanceof \Closure) {
            return self::retry($param1, $param2, 0);
        }

        throw new \InvalidArgumentException(
            'Arguments must be of type (Doctrine\DBAL\Connection $connection, \Closure $closure).'
        );
    }

    public function getQuery(): Statement
    {
        return $this->query;
    }

    private static function retry(?Connection $connection, \Closure $closure, int $counter)
    {
        ++$counter;

        try {
            return $closure();
        } catch (RetryableException $e) {
            if ($connection && $connection->getTransactionNestingLevel() > 0) {
                // If this closure was executed inside a transaction, do not retry. Remember that the whole (outermost)
                // transaction was already rolled back by the database when any RetryableException is thrown. Rethrow
                // the exception here so only the outermost transaction is retried which in turn includes this closure.
                throw $e;
            }

            if ($counter > 10) {
                throw $e;
            }

            // randomize sleep to prevent same execution delay for multiple statements
            usleep(random_int(10, 20));

            return self::retry($connection, $closure, $counter);
        }
    }
}

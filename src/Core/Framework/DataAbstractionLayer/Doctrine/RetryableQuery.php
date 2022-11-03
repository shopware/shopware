<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Statement;
use Shopware\Core\Framework\Feature;

class RetryableQuery
{
    /**
     * @var Connection|null
     */
    private $connection;

    private DriverStatement $query;

    /**
     * @param Connection $param1
     * @param Statement|DriverStatement $param2
     */
    public function __construct($param1, $param2 = null)
    {
        if ($param1 instanceof DriverStatement && $param2 === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Only passing a DriverStatement to RetryableQuery::__construct() is deprecated and will not be possible anymore in v6.5.0.0., instead pass a Connection as first parameter and a Doctrine\DBAL\Statement as second.'
            );
            $this->query = $param1;
        } elseif ($param1 instanceof Connection && $param2 instanceof DriverStatement) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Passing a DriverStatement as second argument to RetryableQuery::__construct() is deprecated and will not be possible anymore in v6.5.0.0., instead pass a Doctrine\DBAL\Statement as second parameter.'
            );
            $this->connection = $param1;
            $this->query = $param2;
        } elseif ($param1 instanceof Connection && $param2 instanceof Statement) {
            $this->connection = $param1;
            $this->query = $param2->getWrappedStatement();
        } else {
            throw new \InvalidArgumentException(
                'Constructor arguments must be of type (Doctrine\DBAL\Connection $connection, Doctrine\DBAL\Statement $query).'
            );
        }
    }

    /**
     * @deprecated tag:v6.5.0 - reason:return-type-change - will return the number of affected rows as int in the next major and $params won't allow null anymore and will be an empty array as default value
     */
    public function execute(?array $params = null): bool
    {
        if (\func_num_args() === 0) {
            $params = [];
        }

        if ($params === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Passing null as first parameter to RetryableQuery::execute() is deprecated and will not be possible anymore in v6.5.0.0., instead pass an empty array or omit the parameter completely.'
            );

            $params = [];
        }

        return self::retry($this->connection, function () use ($params) {
            /** @deprecated tag:v6.5.0 - Will execute the DBAL statement instead of the DriverStatement directly */
            $this->query->execute($params);

            return true;
        // return $this->query->executeStatement($params);
        }, 0);
    }

    /**
     * @deprecated tag:v6.5.0 - Params will be natively types and the second param won't allow null anymore, pass a Connection as first parameter and a Closure as second
     *
     * @param Connection $param1
     * @param \Closure   $param2
     */
    public static function retryable($param1, $param2 = null)
    {
        if ($param1 instanceof \Closure && $param2 === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                'Only passing a Closure to RetryableQuery::retryable() is deprecated and will not be possible anymore in v6.5.0.0., instead pass a Connection as first parameter and a Closure as second.'
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

    /**
     * @deprecated tag:v6.5.0 - reason:return-type-change - Will return `Doctrine\DBAL\Statement` in the next major
     */
    public function getQuery(): DriverStatement
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
            usleep(20 * $counter);

            return self::retry($connection, $closure, $counter);
        }
    }
}

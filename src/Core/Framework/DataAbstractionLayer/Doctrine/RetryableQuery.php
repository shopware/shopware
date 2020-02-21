<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Query\QueryBuilder;

class RetryableQuery
{
    /**
     * @var Statement
     */
    private $query;

    public function __construct(Statement $query)
    {
        $this->query = $query;
    }

    public function execute(?array $params = null): bool
    {
        return self::retry(function () use ($params) {
            return $this->query->execute($params);
        }, 1);
    }

    public static function retryable(\Closure $closure)
    {
        return self::retry($closure, 0);
    }

    public static function executeBuilder(QueryBuilder $builder)
    {
        return self::retry(function () use ($builder) {
            return $builder->execute();
        }, 1);
    }

    public function getQuery(): Statement
    {
        return $this->query;
    }

    private static function retry(\Closure $closure, int $counter)
    {
        ++$counter;

        try {
            return $closure();
        } catch (RetryableException $e) {
            if ($counter > 10) {
                throw $e;
            }

            usleep(5);

            return self::retry($closure, $counter);
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;

/**
 * @package checkout
 *
 * @internal
 */
class FakeConnection extends Connection
{
    /**
     * @var array<mixed>
     */
    private array $dbRows;

    /**
     * @param array<mixed> $dbRows
     *
     * @throws Exception
     *
     * @phpstan-ignore-next-line DBAL Connection uses psalm-consistent-constructor annotation,
     * therefore deriving classes should not change the constructor args, as we are in tests we ignore the error
     */
    public function __construct(array $dbRows)
    {
        parent::__construct(
            [
                'url' => 'sqlite:///:memory:',
            ],
            new Driver(),
            new Configuration()
        );

        $this->dbRows = $dbRows;
    }

    public function executeQuery($sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        return new Result(
            new ArrayResult($this->dbRows),
            $this
        );
    }

    /**
     * @return QueryBuilder|FakeQueryBuilder
     */
    public function createQueryBuilder()
    {
        return new FakeQueryBuilder($this, $this->dbRows);
    }
}

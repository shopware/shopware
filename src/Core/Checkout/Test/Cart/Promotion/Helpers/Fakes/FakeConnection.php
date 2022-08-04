<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\PDOMySql\Driver;
use Doctrine\DBAL\ForwardCompatibility\Result;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * @internal
 */
class FakeConnection extends Connection
{
    private array $dbRows;

    /**
     * @throws DBALException
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

    /**
     * @inheritdoc
     */
    public function executeQuery($sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null)
    {
        return new Result(
            new FakeResultStatement($this->dbRows)
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

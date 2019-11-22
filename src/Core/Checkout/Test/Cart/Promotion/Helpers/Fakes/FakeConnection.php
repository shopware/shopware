<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOMySql\Driver;
use Doctrine\DBAL\Query\QueryBuilder;

class FakeConnection extends Connection
{
    /** @var array */
    private $dbRows = [];

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function __construct(array $dbRows)
    {
        parent::__construct(
            [
                'url' => 'sqlite:///:memory:',
            ],
            new Driver(),
            new Configuration(),
            null
        );

        $this->dbRows = $dbRows;
    }

    /**
     * @param string $query
     * @param array  $types
     *
     * @return \Doctrine\DBAL\Driver\ResultStatement|void
     */
    public function executeQuery($query, array $params = [], $types = [], ?QueryCacheProfile $qcp = null)
    {
        return new FakeResultStatement($this->dbRows);
    }

    /**
     * @return QueryBuilder|FakeQueryBuilder
     */
    public function createQueryBuilder()
    {
        return new FakeQueryBuilder($this, $this->dbRows);
    }
}

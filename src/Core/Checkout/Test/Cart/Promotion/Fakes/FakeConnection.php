<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Fakes;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
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
            new \Doctrine\DBAL\Driver\PDOMySql\Driver(),
            new \Doctrine\DBAL\Configuration(),
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

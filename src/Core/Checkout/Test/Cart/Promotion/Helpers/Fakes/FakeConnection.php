<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\PDOMySql\Driver;
use Doctrine\DBAL\Query\QueryBuilder;

class FakeConnection extends Connection
{
    private array $dbRows;

    /**
     * @throws DBALException
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
     * @param string $sql
     * @param array  $types
     *
     * @return \Doctrine\DBAL\ForwardCompatibility\DriverStatement|\Doctrine\DBAL\ForwardCompatibility\DriverResultStatement
     */
    public function executeQuery($sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null)
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

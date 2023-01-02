<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Cache\ArrayStatement;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;

/**
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
     * @throws \Doctrine\DBAL\Exception
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
            new \Doctrine\DBAL\Driver\PDO\MySQL\Driver(),
            new Configuration()
        );

        $this->dbRows = $dbRows;
    }

    /**
     * @return \Doctrine\DBAL\ForwardCompatibility\Result<mixed>
     */
    public function executeQuery($sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        /** @deprecated tag:v6.5.0 - Return `Doctrine\DBAL\Cache\ArrayResult` after DBAL upgrade */
        return new \Doctrine\DBAL\ForwardCompatibility\Result(
            new ArrayStatement($this->dbRows)
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

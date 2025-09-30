<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Doctrine;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class FakeConnection extends Connection
{
    /**
     * @param list<array<array-key, mixed>> $dbRows
     *
     * @throws Exception
     *
     * @phpstan-ignore parameter.missing, parameter.missing
     */
    public function __construct(private readonly array $dbRows)
    {
        parent::__construct(
            [
                'url' => 'sqlite:///:memory:',
            ],
            new Driver(),
            new Configuration()
        );
    }

    public function executeQuery(string $sql, array $params = [], array $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        return FakeResultFactory::createResult($this->dbRows, $this);
    }

    public function createQueryBuilder(): QueryBuilder|FakeQueryBuilder
    {
        return new FakeQueryBuilder($this, $this->dbRows);
    }
}

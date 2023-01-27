<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class FakeQueryBuilder extends QueryBuilder
{
    private readonly Connection $connection;

    /**
     * @param array<mixed> $dbRows
     */
    public function __construct(
        Connection $connection,
        private readonly array $dbRows
    ) {
        parent::__construct($connection);
        $this->connection = $connection;
    }

    public function execute(): Result|int|string
    {
        return new Result(
            new ArrayResult($this->dbRows),
            $this->connection
        );
    }

    public function executeQuery(): Result
    {
        return new Result(
            new ArrayResult($this->dbRows),
            $this->connection
        );
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;

/**
 * @internal
 */
class FakeQueryBuilder extends QueryBuilder
{
    /**
     * @var array<mixed>
     */
    private array $dbRows = [];

    private Connection $connection;

    /**
     * @param array<mixed> $dbRows
     */
    public function __construct(Connection $connection, array $dbRows)
    {
        parent::__construct($connection);

        $this->dbRows = $dbRows;
        $this->connection = $connection;
    }

    /**
     * @return Result|int|string
     */
    public function execute()
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

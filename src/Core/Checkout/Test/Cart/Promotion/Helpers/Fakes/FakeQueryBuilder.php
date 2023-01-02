<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Cache\ArrayStatement;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ForwardCompatibility\Result;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * @internal
 */
class FakeQueryBuilder extends QueryBuilder
{
    /**
     * @var array<mixed>
     */
    private array $dbRows = [];

    /**
     * @param array<mixed> $dbRows
     */
    public function __construct(Connection $connection, array $dbRows)
    {
        parent::__construct($connection);

        $this->dbRows = $dbRows;
    }

    /**
     * @return \Doctrine\DBAL\Result|int
     */
    public function execute()
    {
        /** @deprecated tag:v6.5.0 - Return `Doctrine\DBAL\Cache\ArrayResult` after DBAL upgrade */
        return new Result(
            new ArrayStatement($this->dbRows)
        );
    }

    public function executeQuery(): \Doctrine\DBAL\Result
    {
        /** @deprecated tag:v6.5.0 - Return `Doctrine\DBAL\Cache\ArrayResult` after DBAL upgrade */
        return new Result(
            new ArrayStatement($this->dbRows)
        );
    }
}

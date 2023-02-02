<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

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
    private array $dbRows;

    /**
     * @param array<mixed> $dbRows
     */
    public function __construct(Connection $connection, array $dbRows)
    {
        parent::__construct($connection);

        $this->dbRows = $dbRows;
    }

    /**
     * @return Result<mixed>|int|FakeResultStatement
     */
    public function execute()
    {
        return new FakeResultStatement($this->dbRows);
    }
}

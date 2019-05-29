<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class FakeQueryBuilder extends QueryBuilder
{
    /** @var array */
    private $dbRows = [];

    public function __construct(Connection $connection, array $dbRows)
    {
        parent::__construct($connection);

        $this->dbRows = $dbRows;
    }

    /**
     * @return \Doctrine\DBAL\Driver\Statement|int|FakeResultStatement
     */
    public function execute()
    {
        return new FakeResultStatement($this->dbRows);
    }
}

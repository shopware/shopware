<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Driver\ResultStatement;

class FakeResultStatement implements \IteratorAggregate, ResultStatement
{
    /**
     * @var array
     */
    private $dbResult = [];

    public function __construct(array $dbResult)
    {
        $this->dbResult = $dbResult;
    }

    public function closeCursor(): bool
    {
        return true;
    }

    public function columnCount(): int
    {
        return 0;
    }

    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null): bool
    {
        return true;
    }

    public function fetch($fetchMode = null, $cursorOrientation = \PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        return $this->dbResult;
    }

    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        return $this->dbResult;
    }

    public function fetchColumn($columnIndex = 0)
    {
        return '';
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(get_object_vars($this));
    }
}

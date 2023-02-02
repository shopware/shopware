<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ResultStatement;

/**
 * @internal
 *
 * @implements \IteratorAggregate<mixed>
 */
class FakeResultStatement implements \IteratorAggregate, ResultStatement, Result
{
    /**
     * @var array<mixed>
     */
    private array $dbResult;

    /**
     * @param array<mixed> $dbResult
     */
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

    public function rowCount(): int
    {
        return \count($this->dbResult);
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

    public function fetchNumeric()
    {
        return $this->dbResult;
    }

    public function fetchAssociative()
    {
        return $this->dbResult;
    }

    public function fetchOne()
    {
        return array_shift($this->dbResult);
    }

    public function fetchAllNumeric(): array
    {
        return $this->dbResult;
    }

    public function fetchAllAssociative(): array
    {
        return $this->dbResult;
    }

    public function fetchFirstColumn(): array
    {
        return $this->dbResult;
    }

    public function free(): void
    {
    }
}

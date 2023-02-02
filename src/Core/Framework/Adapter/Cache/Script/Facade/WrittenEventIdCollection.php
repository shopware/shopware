<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Script\Facade;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;

/**
 * @implements \IteratorAggregate<int, string|array>
 */
class WrittenEventIdCollection implements \IteratorAggregate
{
    /**
     * @var EntityWriteResult[]
     */
    private array $writeResults;

    /**
     * @param EntityWriteResult[] $writeResults
     */
    public function __construct(array $writeResults)
    {
        $this->writeResults = $writeResults;
    }

    /**
     * `only()` filters the writeResults by the given operation names and returns a new collection.
     *
     * @param string ...$operations The operations which should be filters, one of `insert`, `update` od `delete`.
     */
    public function only(string ...$operations): self
    {
        $writeResults = array_filter($this->writeResults, function (EntityWriteResult $result) use ($operations): bool {
            return \in_array($result->getOperation(), $operations, true);
        });

        return new self($writeResults);
    }

    /**
     * `with()` filters the writeResults by changes to the given properties and returns a new collection.
     * At least one of the given properties need to be in the change-set.
     *
     * @param string ...$properties The properties that should be in the change-set of the writeResult.
     */
    public function with(string ...$properties): self
    {
        $writeResults = array_filter($this->writeResults, function (EntityWriteResult $result) use ($properties): bool {
            return \count(\array_intersect(array_keys($result->getPayload()), $properties)) > 0;
        });

        return new self($writeResults);
    }

    public function empty(): bool
    {
        return \count($this->writeResults) < 1;
    }

    /**
     * @internal should not be used directly, loop over an ItemsFacade directly inside twig instead
     *
     * @return \ArrayIterator<int, string|array>
     */
    public function getIterator(): \ArrayIterator
    {
        $primaryKeys = \array_map(function (EntityWriteResult $result) {
            return $result->getPrimaryKey();
        }, $this->writeResults);

        return new \ArrayIterator($primaryKeys);
    }
}

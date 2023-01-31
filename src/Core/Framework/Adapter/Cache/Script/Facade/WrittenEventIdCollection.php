<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Script\Facade;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements \IteratorAggregate<int, string|array>
 */
#[Package('core')]
class WrittenEventIdCollection implements \IteratorAggregate
{
    /**
     * @param EntityWriteResult[] $writeResults
     */
    public function __construct(private readonly array $writeResults)
    {
    }

    /**
     * `only()` filters the writeResults by the given operation names and returns a new collection.
     *
     * @param string ...$operations The operations which should be filters, one of `insert`, `update` od `delete`.
     */
    public function only(string ...$operations): self
    {
        $writeResults = array_filter($this->writeResults, fn (EntityWriteResult $result): bool => \in_array($result->getOperation(), $operations, true));

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
        $writeResults = array_filter($this->writeResults, fn (EntityWriteResult $result): bool => \count(\array_intersect(array_keys($result->getPayload()), $properties)) > 0);

        return new self($writeResults);
    }

    public function empty(): bool
    {
        return \count($this->writeResults) < 1;
    }

    /**
     * @internal should not be used directly, loop over an ItemsFacade directly inside twig instead
     *
     * @return \ArrayIterator<int, string|array<string, string>>
     */
    public function getIterator(): \ArrayIterator
    {
        $primaryKeys = array_values(\array_map(fn (EntityWriteResult $result) => $result->getPrimaryKey(), $this->writeResults));

        return new \ArrayIterator($primaryKeys);
    }
}

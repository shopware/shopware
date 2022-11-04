<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver\Element;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * @implements \IteratorAggregate<array-key, \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult>
 */
class ElementDataCollection implements \IteratorAggregate, \Countable
{
    protected array $searchResults = [];

    public function add(string $key, EntitySearchResult $entitySearchResult): void
    {
        $this->searchResults[$key] = $entitySearchResult;
    }

    public function get(string $key): ?EntitySearchResult
    {
        return $this->searchResults[$key] ?? null;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->searchResults;
    }

    public function count(): int
    {
        return \count($this->searchResults);
    }
}

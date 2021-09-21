<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver;

use Shopware\Core\Content\Cms\Exception\DuplicateCriteriaKeyException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CriteriaCollection implements \IteratorAggregate
{
    /**
     * @var array<string, array<string, Criteria>>
     */
    private $elements = [];

    /**
     * @var bool[]
     */
    private $keys = [];

    public function add(string $key, string $definition, Criteria $criteria): void
    {
        if (isset($this->keys[$key])) {
            throw new DuplicateCriteriaKeyException($key);
        }

        $this->elements[$definition][$key] = $criteria;
        $this->keys[$key] = true;
    }

    public function all(): array
    {
        return $this->elements;
    }

    /**
     * @deprecated tag:v6.5.0 - Return type will be changed to \Traversable
     */
    #[\ReturnTypeWillChange]
    public function getIterator()/* :\Traversable */
    {
        yield from $this->elements;
    }
}

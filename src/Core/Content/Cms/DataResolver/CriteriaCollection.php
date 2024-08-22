<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver;

use Shopware\Core\Content\Cms\CmsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements \IteratorAggregate<string, array<string, Criteria>>
 */
#[Package('buyers-experience')]
class CriteriaCollection implements \IteratorAggregate
{
    /**
     * @var array<string, array<string, Criteria>>
     */
    private array $elements = [];

    /**
     * @var array<string, bool>
     */
    private array $keys = [];

    public function add(string $key, string $definition, Criteria $criteria): void
    {
        if (isset($this->keys[$key])) {
            throw CmsException::duplicateCriteriaKey($key);
        }

        $this->elements[$definition][$key] = $criteria;
        $this->keys[$key] = true;
    }

    /**
     * @return array<string, array<string, Criteria>>
     */
    public function all(): array
    {
        return $this->elements;
    }

    /**
     * @return \Generator<string, array<string, Criteria>>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->elements;
    }
}

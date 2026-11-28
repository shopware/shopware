<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util\Stub;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;

/**
 * @internal
 *
 * @extends EntityCollection<TestEntity>
 */
class AfterSortCollection extends EntityCollection
{
    public function sortByAfter(): self
    {
        $this->elements = AfterSort::sort($this->elements);

        return $this;
    }

    protected function getExpectedClass(): string
    {
        return TestEntity::class;
    }
}

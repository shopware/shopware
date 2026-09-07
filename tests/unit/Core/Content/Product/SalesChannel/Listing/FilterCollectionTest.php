<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Content\Product\SalesChannel\Listing\FilterCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(FilterCollection::class)]
class FilterCollectionTest extends TestCase
{
    public function testSetIgnoresNull(): void
    {
        $collection = new FilterCollection();
        $collection->set('empty', null);

        static::assertCount(0, $collection);
    }

    public function testBlacklistReturnsANewCollectionWithoutTheExcludedKey(): void
    {
        $collection = new FilterCollection();
        $collection->set('manufacturer', $this->createFilter('manufacturer'));
        $collection->set('price', $this->createFilter('price'));

        $filtered = $collection->blacklist('price');

        static::assertNotSame($collection, $filtered);
        static::assertSame(['manufacturer'], $filtered->getKeys());
        static::assertSame(['manufacturer', 'price'], $collection->getKeys());
    }

    private function createFilter(string $name): Filter
    {
        return new Filter($name, true, [], new EqualsFilter('product.id', null), null);
    }
}

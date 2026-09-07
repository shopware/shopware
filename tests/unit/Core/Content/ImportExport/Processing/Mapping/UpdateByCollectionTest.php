<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Processing\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Processing\Mapping\UpdateBy;
use Shopware\Core\Content\ImportExport\Processing\Mapping\UpdateByCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(UpdateByCollection::class)]
class UpdateByCollectionTest extends TestCase
{
    public function testFromIterableReturnsAnExistingCollectionUnchanged(): void
    {
        $collection = new UpdateByCollection();

        static::assertSame($collection, UpdateByCollection::fromIterable($collection));
    }

    public function testFromIterableBuildsEntriesFromStringsAndArrays(): void
    {
        $collection = UpdateByCollection::fromIterable([
            'product',
            ['entityName' => 'category', 'mappedKey' => 'id'],
        ]);

        static::assertCount(2, $collection);
        $first = $collection->first();
        $last = $collection->last();
        static::assertInstanceOf(UpdateBy::class, $first);
        static::assertInstanceOf(UpdateBy::class, $last);
        static::assertSame('product', $first->getEntityName());
        static::assertSame('category', $last->getEntityName());
        static::assertSame('id', $last->getMappedKey());
    }
}

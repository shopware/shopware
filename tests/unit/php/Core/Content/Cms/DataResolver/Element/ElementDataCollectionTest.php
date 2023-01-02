<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection
 */
class ElementDataCollectionTest extends TestCase
{
    public function testItIterates(): void
    {
        $collection = new ElementDataCollection();
        $collection->add('a', new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        static::assertInstanceOf(\IteratorAggregate::class, $collection);
        static::assertCount(1, $collection);
        static::assertContainsOnly(EntitySearchResult::class, $collection);
    }
}

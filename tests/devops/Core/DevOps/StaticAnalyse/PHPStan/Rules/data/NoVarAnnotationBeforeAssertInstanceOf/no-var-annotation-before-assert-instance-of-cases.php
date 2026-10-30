<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\NoVarAnnotationBeforeAssertInstanceOfFixture;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;

/**
 * @internal
 */
class NoVarAnnotationBeforeAssertInstanceOfCases extends TestCase
{
    public function testAssertedOnTheNextLine(): void
    {
        /** @var ProductEntity $product */
        $product = (new ProductCollection([new ProductEntity()]))->first();
        static::assertInstanceOf(ProductEntity::class, $product);
    }

    public function testUnnamedAnnotationAssertedLater(): void
    {
        $collection = new ProductCollection([new ProductEntity()]);
        /** @var ProductEntity */
        $product = $collection->first();
        $count = $collection->count();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame(1, $count);
    }

    public function testInsideANestedBlock(): void
    {
        foreach ([new ProductCollection([new ProductEntity()])] as $collection) {
            /** @var ProductEntity $product */
            $product = $collection->first();
            static::assertInstanceOf(ProductEntity::class, $product);
        }
    }

    public function testUsedBeforeTheAssertion(): void
    {
        /** @var ProductEntity $product */
        $product = (new ProductCollection([new ProductEntity()]))->first();
        $id = $product->getId();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame($id, $product->getId());
    }

    public function testAssertingAnotherClass(): void
    {
        /** @var ProductEntity $product */
        $product = (new ProductCollection([new ProductEntity()]))->first();
        static::assertInstanceOf(\stdClass::class, $product);
    }

    public function testNullableAnnotation(): void
    {
        /** @var ProductEntity|null $product */
        $product = (new ProductCollection([new ProductEntity()]))->first();
        static::assertInstanceOf(ProductEntity::class, $product);
    }

    public function testNoAssertion(): void
    {
        /** @var ProductEntity $product */
        $product = (new ProductCollection([new ProductEntity()]))->first();
        static::assertSame('', $product->getId());
    }
}

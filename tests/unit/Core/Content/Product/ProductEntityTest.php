<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductEntity::class)]
class ProductEntityTest extends TestCase
{
    public function testStringify(): void
    {
        $entity = new ProductEntity();
        $entity->setId('fooId');

        static::assertSame('', (string) $entity);

        $entity->setName('foo');

        static::assertSame('foo', (string) $entity);

        $entity->setTranslated([
            'name' => 'translated foo',
        ]);

        static::assertSame('translated foo', (string) $entity);
    }

    // @deprecated tag:v6.8.0 - Remove, the accessors no longer exist
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testAvailableStockAccessorsAreDeprecated(): void
    {
        $entity = new ProductEntity();

        static::assertNull($entity->getAvailableStock());

        $entity->setAvailableStock(5);

        static::assertSame(5, $entity->getAvailableStock());
    }

    public function testGetAvailableStockThrowsWithTheNextMajor(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: '
            . Feature::deprecatedMethodMessage(ProductEntity::class, 'getAvailableStock', 'v6.8.0.0', 'getStock')
        ));

        (new ProductEntity())->getAvailableStock();
    }

    public function testSetAvailableStockThrowsWithTheNextMajor(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: '
            . Feature::deprecatedMethodMessage(ProductEntity::class, 'setAvailableStock', 'v6.8.0.0', 'setStock')
        ));

        (new ProductEntity())->setAvailableStock(5);
    }
}

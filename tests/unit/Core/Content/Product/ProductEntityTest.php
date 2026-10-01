<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;

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

    public function testIsGuaranteeConfirmedTreatsAnInheritedValueAsUnconfirmed(): void
    {
        $product = new ProductEntity();

        static::assertNull($product->get('guaranteeConfirmed'));
        static::assertFalse($product->isGuaranteeConfirmed());

        $product->setGuaranteeConfirmed(true);

        static::assertTrue($product->isGuaranteeConfirmed());
    }
}

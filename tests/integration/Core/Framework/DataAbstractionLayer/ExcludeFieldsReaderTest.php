<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ExcludeFieldsReaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExcludedFieldIsNotLoadedButEntityStaysFull(): void
    {
        $id = $this->createProduct();
        $context = Context::createDefaultContext();

        $criteria = new Criteria([$id]);
        $criteria->excludeFields(['description']);

        $product = static::getContainer()->get('product.repository')->search($criteria, $context)->get($id);

        // Unlike addFields(), the result is the full, typed entity — not a PartialEntity.
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertNotInstanceOf(PartialEntity::class, $product);

        // The excluded (nullable) field is left null; everything else loads as usual.
        static::assertNull($product->getDescription());
        static::assertSame('Exclude probe', $product->getName());
        static::assertNotEmpty($product->getProductNumber());
    }

    public function testExcludingNonNullableFieldThrows(): void
    {
        $context = Context::createDefaultContext();

        // `stock` is a non-nullable property with no default; excluding it would leave the typed
        // property uninitialized, so the reader must reject it.
        $criteria = new Criteria([Uuid::randomHex()]);
        $criteria->excludeFields(['stock']);

        $this->expectException(DataAbstractionLayerException::class);
        static::getContainer()->get('product.repository')->search($criteria, $context);
    }

    private function createProduct(): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('product.repository')->create([[
            'id' => $id,
            'productNumber' => $id,
            'name' => 'Exclude probe',
            'description' => '<p>Heavy description</p>',
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
            'tax' => ['name' => 'probe', 'taxRate' => 19],
        ]], Context::createDefaultContext());

        return $id;
    }
}

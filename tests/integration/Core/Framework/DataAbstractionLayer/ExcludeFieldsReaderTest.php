<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class ExcludeFieldsReaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testExcludedFieldIsNotLoadedButEntityStaysFull(): void
    {
        $id = $this->createProduct();
        $context = Context::createDefaultContext();

        $criteria = new Criteria([$id]);
        $criteria->excludeFields(['description']);

        $product = static::getContainer()->get('product.repository')->search($criteria, $context)->getEntities()->get($id);

        // Unlike addFields(), the result is the full, typed ProductEntity (not a PartialEntity).
        static::assertInstanceOf(ProductEntity::class, $product);

        // The excluded (nullable) field is left null; everything else loads as usual.
        static::assertNull($product->getDescription());
        static::assertSame('Exclude probe', $product->getName());
        static::assertNotEmpty($product->getProductNumber());
    }

    #[DataProvider('protectedFieldProvider')]
    public function testExcludingProtectedFieldThrows(string $field): void
    {
        $context = Context::createDefaultContext();

        // Required (`stock`) and write-protected (`available`) fields back non-nullable entity
        // properties, so the reader must reject excluding them.
        $criteria = new Criteria([Uuid::randomHex()]);
        $criteria->excludeFields([$field]);

        $this->expectException(DataAbstractionLayerException::class);
        static::getContainer()->get('product.repository')->search($criteria, $context);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function protectedFieldProvider(): iterable
    {
        yield 'required field' => ['stock'];
        yield 'write-protected field' => ['available'];
    }

    public function testExcludingUnknownFieldThrows(): void
    {
        $context = Context::createDefaultContext();

        // A typo / non-existent field must fail loudly instead of being silently ignored.
        $criteria = new Criteria([Uuid::randomHex()]);
        $criteria->excludeFields(['descriptionn']);

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

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductAvailabilityTwigFunction;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductAvailabilityTwigFunction::class)]
class ProductAvailabilityTwigFunctionTest extends TestCase
{
    public function testGetFunctionsRegistersSwProductAvailable(): void
    {
        $extension = $this->createExtension([]);

        $functions = $extension->getFunctions();

        static::assertCount(1, $functions);
        static::assertSame('sw_product_available', $functions[0]->getName());
    }

    public function testIsAvailableReturnsFalseForNullProductId(): void
    {
        $extension = $this->createExtension([]);

        static::assertFalse($extension->isAvailable(null, Context::createDefaultContext()));
    }

    public function testIsAvailableReturnsTrueWhenTheProductIsActive(): void
    {
        $productId = 'product-id';
        $extension = $this->createExtension([[$productId]]);

        static::assertTrue($extension->isAvailable($productId, Context::createDefaultContext()));
    }

    public function testIsAvailableReturnsFalseWhenTheProductIsDeactivatedOrDeleted(): void
    {
        $productId = 'product-id';
        $extension = $this->createExtension([[]]);

        static::assertFalse($extension->isAvailable($productId, Context::createDefaultContext()));
    }

    /**
     * @param list<mixed> $searchResults
     */
    private function createExtension(array $searchResults): ProductAvailabilityTwigFunction
    {
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository($searchResults, new ProductDefinition());

        return new ProductAvailabilityTwigFunction($productRepository);
    }
}

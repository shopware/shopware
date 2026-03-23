<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Provider\AbstractProductExportProvider;
use Shopware\Core\Content\ProductExport\Provider\ProductExportProviderRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductExportProviderRegistry::class)]
class ProductExportProviderRegistryTest extends TestCase
{
    public function testGetByTechnicalNameReturnsMatchingProvider(): void
    {
        $firstProvider = $this->createProvider('google');
        $matchingProvider = $this->createProvider('open-ai');
        $duplicateProvider = $this->createProvider('open-ai');

        $registry = new ProductExportProviderRegistry([
            $firstProvider,
            $matchingProvider,
            $duplicateProvider,
        ]);

        static::assertSame($matchingProvider, $registry->getByTechnicalName('open-ai'));
    }

    public function testGetByTechnicalNameReturnsNullWhenProviderDoesNotExist(): void
    {
        $registry = new ProductExportProviderRegistry([
            $this->createProvider('google'),
            $this->createProvider('meta'),
        ]);

        static::assertNull($registry->getByTechnicalName('open-ai'));
    }

    private function createProvider(string $technicalName): AbstractProductExportProvider
    {
        return new class($technicalName) extends AbstractProductExportProvider {
            public function __construct(private readonly string $technicalName)
            {
            }

            public function getTechnicalName(): string
            {
                return $this->technicalName;
            }

            public function extendRenderContext(
                ProductExportEntity $productExport,
                SalesChannelContext $salesChannelContext,
                array $renderContext
            ): array {
                return $renderContext;
            }
        };
    }
}

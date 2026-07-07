<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Provider\AbstractAgenticCommerceProductExportProvider;
use Shopware\Core\Content\ProductExport\Provider\AgenticCommerceProductExportProviderRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AgenticCommerceProductExportProviderRegistry::class)]
class AgenticCommerceProductExportProviderRegistryTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testGetByTechnicalNameReturnsMatchingProvider(): void
    {
        $this->expectUserDeprecationMessage('Class "Shopware\\Core\\Content\\ProductExport\\Provider\\AgenticCommerceProductExportProviderRegistry" is deprecated and will be removed in v6.8.0.0. Use "Will be part of SwagAgenticCommerce" instead.');

        $firstProvider = $this->createProvider('google');
        $matchingProvider = $this->createProvider('open-ai');
        $duplicateProvider = $this->createProvider('open-ai');

        $registry = new AgenticCommerceProductExportProviderRegistry([
            $firstProvider,
            $matchingProvider,
            $duplicateProvider,
        ]);

        static::assertSame($matchingProvider, $registry->getByTechnicalName('open-ai'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testGetByTechnicalNameReturnsNullWhenProviderDoesNotExist(): void
    {
        $this->expectUserDeprecationMessage('Class "Shopware\\Core\\Content\\ProductExport\\Provider\\AgenticCommerceProductExportProviderRegistry" is deprecated and will be removed in v6.8.0.0. Use "Will be part of SwagAgenticCommerce" instead.');

        $registry = new AgenticCommerceProductExportProviderRegistry([
            $this->createProvider('google'),
            $this->createProvider('meta'),
        ]);

        static::assertNull($registry->getByTechnicalName('open-ai'));
    }

    private function createProvider(string $technicalName): AbstractAgenticCommerceProductExportProvider
    {
        return new class($technicalName) extends AbstractAgenticCommerceProductExportProvider {
            public function __construct(private readonly string $technicalName)
            {
            }

            public function getTechnicalName(): string
            {
                return $this->technicalName;
            }

            protected function buildProviderContext(
                ProductExportEntity $productExport,
                SalesChannelContext $salesChannelContext,
            ): array {
                return [];
            }
        };
    }
}

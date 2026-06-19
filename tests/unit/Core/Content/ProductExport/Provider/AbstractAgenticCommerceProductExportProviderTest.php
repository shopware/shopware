<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Provider\AbstractAgenticCommerceProductExportProvider;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractAgenticCommerceProductExportProvider::class)]
class AbstractAgenticCommerceProductExportProviderTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextAddsProviderStruct(): void
    {
        $provider = $this->createProvider();

        $agenticChannel = new SalesChannelEntity();
        $agenticChannel->setConfiguration(['affiliateCode' => 'aff-1', 'campaignCode' => 'camp-1']);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $productExport = new ProductExportEntity();
        $productExport->setSalesChannelId('agentic-channel-id');
        $productExport->setSalesChannel($agenticChannel);

        $result = $provider->extendRenderContext($productExport, $context, []);

        static::assertArrayHasKey('provider', $result);
        static::assertInstanceOf(ArrayStruct::class, $result['provider']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextUsesOwnTrackingCodes(): void
    {
        $provider = $this->createProvider(['extra' => 'value']);

        $agenticChannel = new SalesChannelEntity();
        $agenticChannel->setConfiguration([
            'affiliateCode' => 'affiliate-1',
            'campaignCode' => 'campaign-1',
        ]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $productExport = new ProductExportEntity();
        $productExport->setSalesChannelId('agentic-channel-id');
        $productExport->setSalesChannel($agenticChannel);

        $result = $provider->extendRenderContext($productExport, $context, []);

        $provider = $result['provider'];
        static::assertInstanceOf(ArrayStruct::class, $provider);
        static::assertSame('affiliate-1', $provider->get('affiliateCode'));
        static::assertSame('campaign-1', $provider->get('campaignCode'));
        static::assertSame('value', $provider->get('extra'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextWithNoConfiguration(): void
    {
        $provider = $this->createProvider();

        $agenticChannel = new SalesChannelEntity();
        $agenticChannel->setConfiguration([]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $productExport = new ProductExportEntity();
        $productExport->setSalesChannelId('agentic-channel-id');
        $productExport->setSalesChannel($agenticChannel);

        $result = $provider->extendRenderContext($productExport, $context, []);

        $providerStruct = $result['provider'];
        static::assertInstanceOf(ArrayStruct::class, $providerStruct);
        static::assertNull($providerStruct->get('affiliateCode'));
        static::assertNull($providerStruct->get('campaignCode'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextIncludesReferralCodeAndName(): void
    {
        $provider = $this->createProvider();

        $agenticChannel = new SalesChannelEntity();
        $agenticChannel->setConfiguration([]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $productExport = new ProductExportEntity();
        $productExport->setSalesChannelId('agentic-channel-id');
        $productExport->setSalesChannel($agenticChannel);

        $result = $provider->extendRenderContext($productExport, $context, []);

        $providerStruct = $result['provider'];
        static::assertInstanceOf(ArrayStruct::class, $providerStruct);
        static::assertSame('test-provider', $providerStruct->get('name'));
        static::assertSame('agentic-channel-id', $providerStruct->get(SalesChannelTrackingListener::QUERY_PARAM));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextMergesWithExistingContext(): void
    {
        $provider = $this->createProvider();

        $agenticChannel = new SalesChannelEntity();
        $agenticChannel->setConfiguration([]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $existing = ['key' => 'value'];
        $productExport = new ProductExportEntity();
        $productExport->setSalesChannelId('agentic-channel-id');
        $productExport->setSalesChannel($agenticChannel);

        $result = $provider->extendRenderContext($productExport, $context, $existing);

        static::assertArrayHasKey('key', $result);
        static::assertArrayHasKey('provider', $result);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testExtendRenderContextWithNullSalesChannelConfiguration(): void
    {
        $provider = $this->createProvider();

        $agenticChannel = new SalesChannelEntity();
        // configuration not set (null)

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $productExport = new ProductExportEntity();
        $productExport->setSalesChannelId('agentic-channel-id');
        $productExport->setSalesChannel($agenticChannel);

        $result = $provider->extendRenderContext($productExport, $context, []);

        $providerStruct = $result['provider'];
        static::assertInstanceOf(ArrayStruct::class, $providerStruct);
        static::assertNull($providerStruct->get('affiliateCode'));
        static::assertNull($providerStruct->get('campaignCode'));
    }

    /**
     * @param array<string, mixed> $extraProviderContext
     */
    private function createProvider(array $extraProviderContext = []): AbstractAgenticCommerceProductExportProvider
    {
        return new class($extraProviderContext) extends AbstractAgenticCommerceProductExportProvider {
            /**
             * @param array<string, mixed> $extra
             */
            public function __construct(private readonly array $extra = [])
            {
            }

            public function getTechnicalName(): string
            {
                return 'test-provider';
            }

            protected function buildProviderContext(
                ProductExportEntity $productExport,
                SalesChannelContext $salesChannelContext,
            ): array {
                return $this->extra;
            }
        };
    }
}

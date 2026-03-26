<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Provider\AbstractAgenticCommerceProductExportProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractAgenticCommerceProductExportProvider::class)]
class AbstractAgenticCommerceProductExportProviderTest extends TestCase
{
    public function testExtendRenderContextAddsProviderStruct(): void
    {
        $provider = $this->createProvider();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setConfiguration(['affiliateCode' => 'aff-1', 'campaignCode' => 'camp-1']);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $result = $provider->extendRenderContext(new ProductExportEntity(), $context, []);

        static::assertArrayHasKey('provider', $result);
        static::assertInstanceOf(ArrayStruct::class, $result['provider']);
    }

    public function testExtendRenderContextUsesOwnTrackingCodes(): void
    {
        $provider = $this->createProvider(['extra' => 'value']);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setConfiguration([
            'affiliateCode' => 'affiliate-1',
            'campaignCode' => 'campaign-1',
        ]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $result = $provider->extendRenderContext(new ProductExportEntity(), $context, []);

        $provider = $result['provider'];
        static::assertInstanceOf(ArrayStruct::class, $provider);
        static::assertSame('affiliate-1', $provider->get('affiliateCode'));
        static::assertSame('campaign-1', $provider->get('campaignCode'));
        static::assertSame('value', $provider->get('extra'));
    }

    public function testExtendRenderContextInheritsTrackingCodesFromStorefront(): void
    {
        $provider = $this->createProvider();

        $storefrontChannel = new SalesChannelEntity();
        $storefrontChannel->setConfiguration([
            'affiliateCode' => 'affiliate-1',
            'campaignCode' => 'campaign-1',
        ]);

        $productExport = new ProductExportEntity();
        $productExport->setStorefrontSalesChannel($storefrontChannel);

        $agenticChannel = new SalesChannelEntity();
        $agenticChannel->setConfiguration(['inheritStorefrontTrackingCodes' => true]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($agenticChannel);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $result = $provider->extendRenderContext($productExport, $context, []);

        $providerStruct = $result['provider'];
        static::assertInstanceOf(ArrayStruct::class, $providerStruct);
        static::assertSame('affiliate-1', $providerStruct->get('affiliateCode'));
        static::assertSame('campaign-1', $providerStruct->get('campaignCode'));
    }

    public function testExtendRenderContextWithNoConfiguration(): void
    {
        $provider = $this->createProvider();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setConfiguration([]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $result = $provider->extendRenderContext(new ProductExportEntity(), $context, []);

        $providerStruct = $result['provider'];
        static::assertInstanceOf(ArrayStruct::class, $providerStruct);
        static::assertNull($providerStruct->get('affiliateCode'));
        static::assertNull($providerStruct->get('campaignCode'));
    }

    public function testExtendRenderContextIncludesReferralCodeAndName(): void
    {
        $provider = $this->createProvider();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setConfiguration([]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $result = $provider->extendRenderContext(new ProductExportEntity(), $context, []);

        $providerStruct = $result['provider'];
        static::assertInstanceOf(ArrayStruct::class, $providerStruct);
        static::assertSame('test-provider', $providerStruct->get('name'));
        static::assertSame('channel-id', $providerStruct->get('referralCode'));
    }

    public function testExtendRenderContextMergesWithExistingContext(): void
    {
        $provider = $this->createProvider();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setConfiguration([]);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $existing = ['key' => 'value'];
        $result = $provider->extendRenderContext(new ProductExportEntity(), $context, $existing);

        static::assertArrayHasKey('key', $result);
        static::assertArrayHasKey('provider', $result);
    }

    public function testExtendRenderContextWithNullSalesChannelConfiguration(): void
    {
        $provider = $this->createProvider();

        $salesChannel = new SalesChannelEntity();

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $result = $provider->extendRenderContext(new ProductExportEntity(), $context, []);

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

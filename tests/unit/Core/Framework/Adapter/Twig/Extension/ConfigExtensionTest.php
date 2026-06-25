<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\ConfigExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Twig\TwigFunction;

/**
 * @internal
 */
#[CoversClass(ConfigExtension::class)]
class ConfigExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsConfigFunction(): void
    {
        $extension = new ConfigExtension($this->createMock(SystemConfigService::class));
        $functions = $extension->getFunctions();

        static::assertCount(1, $functions);

        $names = array_map(static fn (TwigFunction $function): string => $function->getName(), $functions);
        static::assertSame(['config'], $names);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedStaticConfigReturnsValueWithoutCallingSystemConfig(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->never())->method('get');

        $extension = new ConfigExtension($systemConfigService);

        static::assertSame(255, $extension->config([], 'seo.descriptionMaxLength'));
        static::assertSame('00B9A8636F954277AE424E6C1C36A1F5', $extension->config([], 'cms.revocationNoticeCmsPageId'));
        static::assertSame('00B9A8636F954277AE424E6C1C36A1F5', $extension->config([], 'cms.taxCmsPageId'));
        static::assertSame('00B9A8636F954277AE424E6C1C36A1F5', $extension->config([], 'cms.tosCmsPageId'));
        static::assertTrue($extension->config([], 'confirm.revocationNotice'));
    }

    public function testStaticConfigThrowsWhenMajorFeatureIsActive(): void
    {
        $extension = new ConfigExtension($this->createMock(SystemConfigService::class));

        $this->expectException(FeatureException::class);

        $extension->config([], 'seo.descriptionMaxLength');
    }

    public function testConfigExtractsSalesChannelIdFromSalesChannelContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->expects($this->once())
            ->method('get')
            ->with('my.key', $salesChannelContext->getSalesChannelId())
            ->willReturn('value');

        $extension = new ConfigExtension($systemConfigService);

        static::assertSame('value', $extension->config(['context' => $salesChannelContext], 'my.key'));
    }

    public function testConfigUsesSalesChannelContextFallback(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->expects($this->once())
            ->method('get')
            ->with('core.basicInformation.shopName', TestDefaults::SALES_CHANNEL)
            ->willReturn('Shopware');

        $extension = new ConfigExtension($systemConfigService);

        static::assertSame('Shopware', $extension->config([
            'context' => Context::createDefaultContext(),
            'salesChannelContext' => $salesChannelContext,
        ], 'core.basicInformation.shopName'));
    }

    public function testConfigExtractsSalesChannelIdFromSalesChannelEntity(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('channel-id-abc');
        $salesChannel->setUniqueIdentifier('channel-id-abc');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->expects($this->once())
            ->method('get')
            ->with('my.key', 'channel-id-abc')
            ->willReturn('value');

        $extension = new ConfigExtension($systemConfigService);

        static::assertSame('value', $extension->config(['salesChannel' => $salesChannel], 'my.key'));
    }

    public function testConfigPassesNullSalesChannelIdWhenNoContextPresent(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->expects($this->once())
            ->method('get')
            ->with('my.key', null)
            ->willReturn(42);

        $extension = new ConfigExtension($systemConfigService);

        static::assertSame(42, $extension->config([], 'my.key'));
    }
}

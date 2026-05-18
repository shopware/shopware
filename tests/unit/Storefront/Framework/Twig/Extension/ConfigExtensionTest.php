<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Twig\Extension\ConfigExtension;
use Shopware\Storefront\Framework\Twig\TemplateConfigAccessor;

/**
 * @internal
 */
#[CoversClass(ConfigExtension::class)]
class ConfigExtensionTest extends TestCase
{
    public function testThemeConfigUsesSalesChannelContextFallback(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $config = $this->createMock(TemplateConfigAccessor::class);
        $config
            ->expects($this->once())
            ->method('theme')
            ->with('sw-logo-desktop', $salesChannelContext, $themeId)
            ->willReturn('logo.png');

        $extension = new ConfigExtension($config);

        static::assertSame('logo.png', $extension->theme([
            'context' => Context::createDefaultContext(),
            'salesChannelContext' => $salesChannelContext,
            'themeId' => $themeId,
        ], 'sw-logo-desktop'));
    }

    public function testConfigUsesSalesChannelContextFallback(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $config = $this->createMock(TemplateConfigAccessor::class);
        $config
            ->expects($this->once())
            ->method('config')
            ->with('core.basicInformation.shopName', TestDefaults::SALES_CHANNEL)
            ->willReturn('Shopware');

        $extension = new ConfigExtension($config);

        static::assertSame('Shopware', $extension->config([
            'context' => Context::createDefaultContext(),
            'salesChannelContext' => $salesChannelContext,
        ], 'core.basicInformation.shopName'));
    }
}

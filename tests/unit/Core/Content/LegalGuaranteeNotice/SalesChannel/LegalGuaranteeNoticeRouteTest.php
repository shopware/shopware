<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\LegalGuaranteeNotice\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer;
use Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(LegalGuaranteeNoticeRoute::class)]
class LegalGuaranteeNoticeRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        $route = new LegalGuaranteeNoticeRoute(
            new StaticSystemConfigService(),
            static::createStub(LegalGuaranteeNoticeRenderer::class),
        );

        $this->expectExceptionObject(new DecorationPatternException(LegalGuaranteeNoticeRoute::class));

        $route->getDecorated();
    }

    public function testLoadReturnsNullWhenToggleIsDisabled(): void
    {
        $renderer = $this->createMock(LegalGuaranteeNoticeRenderer::class);
        $renderer->expects($this->never())->method('renderForLanguage');
        $renderer->expects($this->never())->method('linkForLanguage');

        $route = new LegalGuaranteeNoticeRoute(
            new StaticSystemConfigService(['core.cart.showLegalGuaranteeNotice' => false]),
            $renderer,
        );

        $response = $route->load(Generator::generateSalesChannelContext());

        static::assertNull($response->getObject()->get('svg'));
        static::assertNull($response->getObject()->get('link'));
    }

    public function testLoadRendersNoticeAndLinkWhenToggleIsEnabled(): void
    {
        $context = Generator::generateSalesChannelContext();

        $renderer = $this->createMock(LegalGuaranteeNoticeRenderer::class);
        $renderer->expects($this->once())
            ->method('renderForLanguage')
            ->with($context->getLanguageId())
            ->willReturn('<svg>notice</svg>');
        $renderer->expects($this->once())
            ->method('linkForLanguage')
            ->with($context->getLanguageId())
            ->willReturn('https://europa.eu/youreurope/guarantees');

        $route = new LegalGuaranteeNoticeRoute(
            new StaticSystemConfigService(['core.cart.showLegalGuaranteeNotice' => true]),
            $renderer,
        );

        $response = $route->load($context);

        static::assertSame('<svg>notice</svg>', $response->getObject()->get('svg'));
        static::assertSame('https://europa.eu/youreurope/guarantees', $response->getObject()->get('link'));
    }

    public function testLoadRespectsSalesChannelSpecificOverride(): void
    {
        $context = Generator::generateSalesChannelContext();

        $renderer = $this->createMock(LegalGuaranteeNoticeRenderer::class);
        $renderer->expects($this->never())->method('renderForLanguage');

        $route = new LegalGuaranteeNoticeRoute(
            new StaticSystemConfigService([
                'core.cart.showLegalGuaranteeNotice' => true,
                TestDefaults::SALES_CHANNEL => ['core.cart.showLegalGuaranteeNotice' => false],
            ]),
            $renderer,
        );

        $response = $route->load($context);

        static::assertNull($response->getObject()->get('svg'));
    }
}

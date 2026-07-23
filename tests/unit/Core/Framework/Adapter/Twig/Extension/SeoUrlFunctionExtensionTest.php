<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Framework\Adapter\Twig\Extension\SeoUrlFunctionExtension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SeoUrlFunctionExtension::class)]
class SeoUrlFunctionExtensionTest extends TestCase
{
    private SeoUrlPlaceholderHandlerInterface&MockObject $seoUrlReplacer;

    private EntityRouteResolver&MockObject $entityRouteResolver;

    private SeoUrlFunctionExtension $extension;

    protected function setUp(): void
    {
        $this->seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $this->entityRouteResolver = $this->createMock(EntityRouteResolver::class);

        $this->extension = new SeoUrlFunctionExtension(
            new RoutingExtension($this->createStub(UrlGeneratorInterface::class)),
            $this->seoUrlReplacer,
            $this->entityRouteResolver,
        );
    }

    public function testGetFunctionsExposesSeoUrl(): void
    {
        $this->seoUrlReplacer->expects($this->never())->method('generate');
        $this->entityRouteResolver->expects($this->never())->method('generateSeoUrlPlaceholder');

        $functions = $this->extension->getFunctions();

        static::assertCount(1, $functions);
        static::assertInstanceOf(TwigFunction::class, $functions[0]);
        static::assertSame('seoUrl', $functions[0]->getName());
        static::assertTrue($functions[0]->needsContext());
    }

    public function testRouteNameWithDotUsesPlaceholderHandler(): void
    {
        $parameters = ['productId' => Uuid::randomHex()];

        $this->seoUrlReplacer
            ->expects($this->once())
            ->method('generate')
            ->with('frontend.detail.page', $parameters)
            ->willReturn('placeholder-url');

        $this->entityRouteResolver->expects($this->never())->method('generateSeoUrlPlaceholder');

        static::assertSame('placeholder-url', $this->extension->seoUrl([], 'frontend.detail.page', $parameters));
    }

    public function testEntityNameUsesEntityRouteResolver(): void
    {
        $primaryKey = Uuid::randomHex();

        $this->entityRouteResolver
            ->expects($this->once())
            ->method('generateSeoUrlPlaceholder')
            ->with('product', $primaryKey, false)
            ->willReturn('entity-url');

        $this->seoUrlReplacer->expects($this->never())->method('generate');

        static::assertSame('entity-url', $this->extension->seoUrl([], 'product', [$primaryKey]));
    }

    public function testEntityNameInHeadlessSalesChannelMarksPlaceholderAsHeadless(): void
    {
        $primaryKey = Uuid::randomHex();

        $salesChannelContext = $this->createStub(SalesChannelContext::class);
        $salesChannelContext->method('isHeadless')->willReturn(true);

        $this->entityRouteResolver
            ->expects($this->once())
            ->method('generateSeoUrlPlaceholder')
            ->with('product', $primaryKey, true)
            ->willReturn('headless-url');

        $this->seoUrlReplacer->expects($this->never())->method('generate');

        static::assertSame(
            'headless-url',
            $this->extension->seoUrl(['salesChannelContext' => $salesChannelContext], 'product', [$primaryKey])
        );
    }

    public function testEntityNameInNonHeadlessSalesChannelDoesNotMarkPlaceholderAsHeadless(): void
    {
        $primaryKey = Uuid::randomHex();

        $salesChannelContext = $this->createStub(SalesChannelContext::class);
        $salesChannelContext->method('isHeadless')->willReturn(false);

        $this->entityRouteResolver
            ->expects($this->once())
            ->method('generateSeoUrlPlaceholder')
            ->with('product', $primaryKey, false)
            ->willReturn('entity-url');

        $this->seoUrlReplacer->expects($this->never())->method('generate');

        static::assertSame(
            'entity-url',
            $this->extension->seoUrl(['salesChannelContext' => $salesChannelContext], 'product', [$primaryKey])
        );
    }

    public function testEntityNameWithNonStringPrimaryKeyFallsBackToPlaceholderHandler(): void
    {
        $parameters = [42];

        $this->entityRouteResolver->expects($this->never())->method('generateSeoUrlPlaceholder');

        $this->seoUrlReplacer
            ->expects($this->once())
            ->method('generate')
            ->with('product', $parameters)
            ->willReturn('fallback-url');

        static::assertSame('fallback-url', $this->extension->seoUrl([], 'product', $parameters));
    }

    public function testEntityNameWithoutParametersFallsBackToPlaceholderHandler(): void
    {
        $this->entityRouteResolver->expects($this->never())->method('generateSeoUrlPlaceholder');

        $this->seoUrlReplacer
            ->expects($this->once())
            ->method('generate')
            ->with('product', [])
            ->willReturn('fallback-url');

        static::assertSame('fallback-url', $this->extension->seoUrl([], 'product'));
    }

    public function testUnknownEntityRouteFallsBackToPlaceholderHandler(): void
    {
        $primaryKey = Uuid::randomHex();

        $this->entityRouteResolver
            ->expects($this->once())
            ->method('generateSeoUrlPlaceholder')
            ->with('unknown', $primaryKey, false)
            ->willThrowException(SeoUrlRouteConfigException::routeConfigNotFoundForEntityName('unknown'));

        $this->seoUrlReplacer
            ->expects($this->once())
            ->method('generate')
            ->with('unknown', [$primaryKey])
            ->willReturn('fallback-url');

        static::assertSame('fallback-url', $this->extension->seoUrl([], 'unknown', [$primaryKey]));
    }
}

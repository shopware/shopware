<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Theme\SalesChannelThemeLoader;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilderInterface;
use Shopware\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder
 */
class ThemeNamespaceHierarchyBuilderTest extends TestCase
{
    private ThemeNamespaceHierarchyBuilder $builder;

    public function setUp(): void
    {
        $themeLoader = $this->createMock(SalesChannelThemeLoader::class);
        $this->builder = new ThemeNamespaceHierarchyBuilder(new TestInheritanceBuilder(), $themeLoader);
    }

    public function testThemeNamespaceHierarchyBuilderSubscribesToRequestAndExceptionEvents(): void
    {
        $events = $this->builder->getSubscribedEvents();

        static::assertEquals([
            KernelEvents::REQUEST,
            KernelEvents::EXCEPTION,
            DocumentTemplateRendererParameterEvent::class,
        ], array_keys($events));
    }

    public function testThemesAreEmptyIfRequestHasNoValidAttributes(): void
    {
        $request = Request::createFromGlobals();

        $this->builder->requestEvent(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertThemes([], $this->builder);
    }

    public function testThemesIfThemeNameIsSet(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertThemes([
            'Storefront' => true,
            'TestTheme' => true,
        ], $this->builder);
    }

    /**
     * @dataProvider onRenderingDocumentProvider
     *
     * @param array<string, mixed> $parameters
     * @param array<string, bool> $expectedThemes
     */
    public function testOnRenderingDocument(array $parameters, array $expectedThemes, ?string $usingTheme): void
    {
        $request = Request::createFromGlobals();
        $event = new DocumentTemplateRendererParameterEvent($parameters);
        $themeLoader = $this->createMock(SalesChannelThemeLoader::class);

        $themeLoader->method('load')->willReturn([
            'themeName' => $usingTheme,
            'parentThemeName' => null,
        ]);

        $builder = new ThemeNamespaceHierarchyBuilder(new TestInheritanceBuilder(), $themeLoader);

        $builder->onDocumentRendering($event);

        $this->assertThemes($expectedThemes, $builder);

        $builder = new ThemeNamespaceHierarchyBuilder(new TestInheritanceBuilder(), $themeLoader);

        $builder->requestEvent(new ExceptionEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new \RuntimeException()));

        $this->assertThemes([], $builder);
    }

    public function testRequestEventWithExceptionEvent(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'TestTheme');

        $this->builder->requestEvent(new ExceptionEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new \RuntimeException()));

        $this->assertThemes([
            'Storefront' => true,
            'TestTheme' => true,
        ], $this->builder);
    }

    public function testThemesIfBaseNameIsSet(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, null);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertThemes([
            'Storefront' => true,
            'TestTheme' => true,
        ], $this->builder);
    }

    public function testReset(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, null);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->builder->reset();

        $this->assertThemes([], $this->builder);
    }

    public function testItReturnsItsInputIfNoThemesAreSet(): void
    {
        $bundles = ['a', 'b'];

        $hierarchy = $this->builder->buildNamespaceHierarchy(['a', 'b']);

        static::assertEquals($bundles, $hierarchy);
    }

    public function testItPassesBundlesAndThemesToBuilder(): void
    {
        $bundles = ['a', 'b'];

        $request = Request::createFromGlobals();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $hierarchy = $this->builder->buildNamespaceHierarchy($bundles);

        static::assertEquals([
            'Storefront' => true,
            'TestTheme' => true,
        ], $hierarchy);
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public function onRenderingDocumentProvider(): iterable
    {
        $context = $this->createMock(SalesChannelContext::class);

        yield 'no theme is using' => [
            [
                'context' => $context,
            ],
            [],
            null,
        ];

        yield 'no context in parameters' => [
            [],
            [],
            'SwagTheme',
        ];

        yield 'theme is using' => [
            [
                'context' => $context,
            ],
            [
                'SwagTheme' => true,
                'Storefront' => true,
            ],
            'SwagTheme',
        ];
    }

    /**
     * @param array<string, bool> $expectation
     */
    private function assertThemes(array $expectation, ThemeNamespaceHierarchyBuilder $builder): void
    {
        $refObj = new \ReflectionObject($builder);
        $refProperty = $refObj->getProperty('themes');
        $refProperty->setAccessible(true);

        static::assertEquals($expectation, $refProperty->getValue($builder));
    }
}

/**
 * @internal
 */
class TestInheritanceBuilder implements ThemeInheritanceBuilderInterface
{
    /**
     * @param array<string> $bundles
     * @param array<string> $themes
     *
     * @return array<string>
     */
    public function build(array $bundles, array $themes): array
    {
        return $themes;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Twig\Extension\IconCacheTwigFilter;
use Shopware\Storefront\Framework\Twig\IconExtension;
use Shopware\Storefront\Storefront;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
#[CoversClass(IconCacheTwigFilter::class)]
#[CoversClass(IconExtension::class)]
class IconCacheTwigFilterTest extends TestCase
{
    public function testStorefrontRenderIconCacheEnabled(): void
    {
        $twig = $this->createFinder([
            new BundleFixture('StorefrontTest', __DIR__ . '/fixtures/Storefront/'),
            new BundleFixture('Storefront', \dirname((string) ReflectionHelper::getFilename(Storefront::class))),
        ]);

        $controller = new TestController();
        $controller->setTwig($twig);
        $controller->setContainer($this->getContainer());
        $controller->setTemplateFinder($twig->getExtension(NodeExtension::class)->getFinder());

        $controller->systemConfigService = self::createMock(SystemConfigService::class);
        $controller->systemConfigService->method('get')->willReturn(true);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $rendered = $controller->testRenderStorefront('@StorefrontTest/test/base.html.twig', $salesChannelContext);

        static::assertEquals('<span class="icon icon-minus-large icon-xs icon-filter-panel-item-toggle">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" viewBox="0 0 16 16"><defs><path id="icons-solid-minus-large" d="M2 9h12c.5523 0 1-.4477 1-1s-.4477-1-1-1H2c-.5523 0-1 .4477-1 1s.4477 1 1 1z" /></defs><use xlink:href="#icons-solid-minus-large" fill="#758CA3" fill-rule="evenodd" /></svg>
        </span><span class="icon icon-minus-large icon-xs icon-filter-panel-item-toggle">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" viewBox="0 0 16 16"><use xlink:href="#icons-solid-minus-large" fill="#758CA3" fill-rule="evenodd" /></svg>
        </span><span class="icon icon-minus-small">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" viewBox="0 0 16 16"><defs><path id="icons-solid-minus-small" d="M4.8571 9h6.2858C11.6162 9 12 8.5523 12 8s-.3838-1-.8571-1H4.857C4.3838 7 4 7.4477 4 8s.3838 1 .8571 1z" /></defs><use xlink:href="#icons-solid-minus-small" fill="#758CA3" fill-rule="evenodd" /></svg>
        </span><span class="icon icon-minus">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" viewBox="0 0 16 16"><defs><path id="icons-solid-minus" d="M2.8571 9H13.143c.4732 0 .857-.4477.857-1s-.3838-1-.8571-1H2.857C2.3838 7 2 7.4477 2 8s.3838 1 .8571 1z" /></defs><use xlink:href="#icons-solid-minus" fill="#758CA3" fill-rule="evenodd" /></svg>
        </span><span class="icon icon-minus">
                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24"><defs><path id="icons-default-minus" d="M3 13h18c.5523 0 1-.4477 1-1s-.4477-1-1-1H3c-.5523 0-1 .4477-1 1s.4477 1 1 1z" /></defs><use xlink:href="#icons-default-minus" fill="#758CA3" fill-rule="evenodd" /></svg>
        </span>', $rendered->getContent());
    }

    public function getContainer(): ContainerInterface
    {
        $container = new ContainerBuilder();
        $container->set('request_stack', new RequestStack());
        $container->set('event_dispatcher', new EventDispatcher());

        $placeholder = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $placeholder->method('replace')->willReturnArgument(0);

        $container->set(SeoUrlPlaceholderHandlerInterface::class, $placeholder);

        $mediaUrlHandler = $this->createMock(MediaUrlPlaceholderHandlerInterface::class);
        $mediaUrlHandler->method('replace')->willReturnArgument(0);

        $container->set(MediaUrlPlaceholderHandlerInterface::class, $mediaUrlHandler);

        return $container;
    }

    /**
     * @param Bundle[] $bundles
     */
    private function createFinder(array $bundles): Environment
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Storefront/Resources/views');

        /** @var BundleFixture $bundle */
        foreach ($bundles as $bundle) {
            $directory = $bundle->getPath() . '/Resources/views';
            $loader->addPath($directory);
            $loader->addPath($directory, $bundle->getName());
            if (file_exists($directory . '/../app/storefront/dist')) {
                $loader->addPath($directory . '/../app/storefront/dist', $bundle->getName());
            }
        }

        $twig = new Environment($loader, ['cache' => false]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::any())
            ->method('getBundles')
            ->willReturn($bundles);

        $builder = $this->createMock(BundleHierarchyBuilder::class);
        $builder
            ->method('buildNamespaceHierarchy')
            ->willReturn(['Storefront' => 0]);

        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->expects(static::any())
            ->method('getScopes')
            ->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            sys_get_temp_dir() . '/' . uniqid('twig_test_', true),
            new NamespaceHierarchyBuilder([
                $builder,
            ]),
            $scopeDetector,
        );

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));
        $twig->getExtension(NodeExtension::class)->getFinder();

        $twig->addExtension(new IconCacheTwigFilter());
        $twig->addExtension(new IconExtension());

        return $twig;
    }
}

/**
 * @internal
 */
class TestController extends StorefrontController
{
    public SystemConfigService $systemConfigService;

    private TemplateFinder $templateFinder;

    public function testRenderStorefront(string $view, SalesChannelContext $salesChannelContext): Response
    {
        $this->container->get('request_stack')->push(new Request());
        $current = $this->container->get('request_stack')->getCurrentRequest();

        if (!$current instanceof Request) {
            throw new \RuntimeException('Request not found');
        }

        $current->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $current->attributes->set(RequestTransformer::STOREFRONT_URL, '/');

        return $this->renderStorefront($view);
    }

    public function setTemplateFinder(TemplateFinder $templateFinder): void
    {
        $this->templateFinder = $templateFinder;
    }

    protected function addCartErrors(Cart $cart, ?\Closure $filter = null): void
    {
        parent::addCartErrors($cart, $filter);
    }

    /**
     * @param string $message
     */
    protected function addFlash(string $type, $message): void
    {
        // NOOP
    }

    protected function getTemplateFinder(): TemplateFinder
    {
        return $this->templateFinder;
    }

    protected function getSystemConfigService(): SystemConfigService
    {
        return $this->systemConfigService;
    }
}

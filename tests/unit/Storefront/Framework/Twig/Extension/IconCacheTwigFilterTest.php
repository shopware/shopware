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
            new BundleFixture('Storefront', \dirname((string) ReflectionHelper::getFileName(Storefront::class))),
        ]);

        $container = $this->buildContainer();
        $container->set('twig', $twig);

        $controller = new TestController();
        $controller->setContainer($container);
        $controller->setTemplateFinder($twig->getExtension(NodeExtension::class)->getFinder());

        $controller->systemConfigService = self::createMock(SystemConfigService::class);
        $controller->systemConfigService->method('get')->willReturn(true);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $rendered = $controller->testRenderStorefront('@StorefrontTest/test/base.html.twig', $salesChannelContext);
        static::assertSame(str_replace(' ', '', '<span class="icon icon-minus-large icon-xs icon-filter-panel-item-toggle" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" viewBox="0 0 16 16"><defs><path id="icons-solid-minus-large" d="M2 9h12c.5523 0 1-.4477 1-1s-.4477-1-1-1H2c-.5523 0-1 .4477-1 1s.4477 1 1 1z" /></defs><use xlink:href="#icons-solid-minus-large" fill="#758CA3" fill-rule="evenodd" /></svg>
                    </span><span class="icon icon-minus-large icon-xs icon-filter-panel-item-toggle" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" viewBox="0 0 16 16"><use xlink:href="#icons-solid-minus-large" fill="#758CA3" fill-rule="evenodd" /></svg>
                    </span><span class="icon icon-minus-small" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" viewBox="0 0 16 16"><defs><path id="icons-solid-minus-small" d="M4.8571 9h6.2858C11.6162 9 12 8.5523 12 8s-.3838-1-.8571-1H4.857C4.3838 7 4 7.4477 4 8s.3838 1 .8571 1z" /></defs><use xlink:href="#icons-solid-minus-small" fill="#758CA3" fill-rule="evenodd" /></svg>
                    </span><span class="icon icon-minus" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" viewBox="0 0 16 16"><defs><path id="icons-solid-minus" d="M2.8571 9H13.143c.4732 0 .857-.4477.857-1s-.3838-1-.8571-1H2.857C2.3838 7 2 7.4477 2 8s.3838 1 .8571 1z" /></defs><use xlink:href="#icons-solid-minus" fill="#758CA3" fill-rule="evenodd" /></svg>
                    </span><span class="icon icon-minus" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24"><defs><path id="icons-default-minus" d="M3 13h18c.5523 0 1-.4477 1-1s-.4477-1-1-1H3c-.5523 0-1 .4477-1 1s.4477 1 1 1z" /></defs><use xlink:href="#icons-default-minus" fill="#758CA3" fill-rule="evenodd" /></svg>
                    </span><span class="icon icon-minus">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#icons-default-minus" fill="#758CA3" fill-rule="evenodd" /></svg>
                    </span>
    <span class="icon icon-cart" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24"><defs><path d="M7.8341 20.9863C7.4261 22.1586 6.3113 23 5 23c-1.6569 0-3-1.3431-3-3 0-1.397.9549-2.571 2.2475-2.9048l.4429-1.3286c-1.008-.4238-1.7408-1.3832-1.8295-2.5365l-.7046-9.1593A1.1598 1.1598 0 0 0 1 3c-.5523 0-1-.4477-1-1s.4477-1 1-1c1.651 0 3.0238 1.2712 3.1504 2.9174L23 3.9446c.6306 0 1.1038.5766.9808 1.195l-1.6798 8.4456C22.0218 14.989 20.7899 16 19.3586 16H6.7208l-.4304 1.291a3.0095 3.0095 0 0 1 1.5437 1.7227C7.8881 19.0047 7.9435 19 8 19h8.1707c.4118-1.1652 1.523-2 2.8293-2 1.6569 0 3 1.3431 3 3s-1.3431 3-3 3c-1.3062 0-2.4175-.8348-2.8293-2H8c-.0565 0-.112-.0047-.1659-.0137zm-2.8506-1.9862C4.439 19.009 4 19.4532 4 20c0 .5523.4477 1 1 1s1-.4477 1-1c0-.5467-.4388-.991-.9834-.9999a.9923.9923 0 0 1-.033 0zM6.0231 14h13.3355a1 1 0 0 0 .9808-.805l1.4421-7.2504H4.3064l.5486 7.1321A1 1 0 0 0 5.852 14h.1247a.9921.9921 0 0 1 .0464 0zM19 21c.5523 0 1-.4477 1-1s-.4477-1-1-1-1 .4477-1 1 .4477 1 1 1z" id="icons-default-cart" /></defs><use xlink:href="#icons-default-cart" fill="#758CA3" fill-rule="evenodd" /></svg>
                    </span>    <span class="icon icon-bag" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24"><defs><path d="M5.892 3c.5523 0 1 .4477 1 1s-.4477 1-1 1H3.7895a1 1 0 0 0-.9986.9475l-.7895 15c-.029.5515.3946 1.0221.9987 1.0525h17.8102c.5523 0 1-.4477.9986-1.0525l-.7895-15A1 1 0 0 0 20.0208 5H17.892c-.5523 0-1-.4477-1-1s.4477-1 1-1h2.1288c1.5956 0 2.912 1.249 2.9959 2.8423l.7894 15c.0035.0788.0035.0788.0042.1577 0 1.6569-1.3432 3-3 3H3c-.079-.0007-.079-.0007-.1577-.0041-1.6546-.0871-2.9253-1.499-2.8382-3.1536l.7895-15C.8775 4.249 2.1939 3 3.7895 3H5.892zm4 2c0 .5523-.4477 1-1 1s-1-.4477-1-1V3c0-1.6569 1.3432-3 3-3h2c1.6569 0 3 1.3431 3 3v2c0 .5523-.4477 1-1 1s-1-.4477-1-1V3c0-.5523-.4477-1-1-1h-2c-.5523 0-1 .4477-1 1v2z" id="icons-default-bag" /></defs><use xlink:href="#icons-default-bag" fill="#758CA3" fill-rule="evenodd" /></svg>
                    </span>    <span class="icon icon-minus" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24" height="24" viewBox="0 0 24 24"><use xlink:href="#icons-default-minus" fill="#758CA3" fill-rule="evenodd" /></svg>
                    </span>'), str_replace(' ', '', $rendered->getContent() ?: ''));
    }

    public function buildContainer(): ContainerInterface
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
        $kernel->expects($this->any())
            ->method('getBundles')
            ->willReturn($bundles);

        $builder = $this->createMock(BundleHierarchyBuilder::class);
        $builder
            ->method('buildNamespaceHierarchy')
            ->willReturn(['Storefront' => 0]);

        $scopeDetector = $this->createMock(TemplateScopeDetector::class);
        $scopeDetector->expects($this->any())
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

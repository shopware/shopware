<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Routing\Router as SymfonyRouter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
#[CoversClass(Router::class)]
class RouterTest extends TestCase
{
    #[DataProvider('urlCases')]
    public function testUrls(UrlCase $case): void
    {
        $request = new Request();
        $request->attributes->set(RequestTransformer::SALES_CHANNEL_BASE_URL, $case->baseUrl);

        $stack = new RequestStack([
            $request,
        ]);

        $symfonyRouter = new SymfonyRouter(new Container(), null);
        \Closure::bind(function (): void {
            $routeCollection = new RouteCollection();
            $routeCollection->add('frontend.home.page', new Route('/', ['_controller' => 'Shopware\Storefront\Controller\HomeController::index']));
            $routeCollection->add('frontend.navigation.page', new Route('/navigation/{navigationId}', ['_controller' => 'Shopware\Storefront\Controller\NavigationController::index']));

            $this->collection = $routeCollection;
        }, $symfonyRouter, SymfonyRouter::class)();

        $router = new Router(
            $symfonyRouter,
            $stack
        );
        $router->setContext(new RequestContext('', 'GET', $case->host));

        $url = $router->generate($case->route, $case->params, $case->type);

        static::assertSame($case->expected, $url);
    }

    /**
     * @return UrlCase[][]
     */
    public static function urlCases(): array
    {
        $id = Uuid::randomHex();

        return [
            'test-home-page-without-suffix' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/', '', 'frontend.home.page'),
            ],
            'test-home-page-with-de' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/de/', '/de', 'frontend.home.page'),
            ],
            'test-home-page-with-de-and-slash' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/de/', '/de/', 'frontend.home.page'),
            ],
            'test-home-page-with-de-without-slash' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/de/', 'de', 'frontend.home.page'),
            ],
            'test-home-page-with-null' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, '/', null, 'frontend.home.page'),
            ],
            'test-navigation-page-with-de' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_PATH, "/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
            ],
            'test-home-page-without-suffix-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, '', '', 'frontend.home.page'),
            ],
            'test-home-page-with-de-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, 'de/', '/de', 'frontend.home.page'),
            ],
            'test-home-page-with-de-and-slash-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, 'de/', '/de/', 'frontend.home.page'),
            ],
            'test-home-page-with-de-without-slash-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, 'de/', 'de', 'frontend.home.page'),
            ],
            'test-home-page-with-null-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, '', null, 'frontend.home.page'),
            ],
            'test-navigation-page-with-de-relative' => [
                new UrlCase(UrlGeneratorInterface::RELATIVE_PATH, "de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
            ],
            'test-home-page-without-suffix-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/', '', 'frontend.home.page'),
            ],
            'test-home-page-with-de-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/', '/de', 'frontend.home.page'),
            ],
            'test-home-page-with-de-and-slash-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/', '/de/', 'frontend.home.page'),
            ],
            'test-home-page-with-de-without-slash-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/de/', 'de', 'frontend.home.page'),
            ],
            'test-home-page-with-null-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de/', null, 'frontend.home.page'),
            ],
            'test-navigation-page-with-de-absolute-url' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, "http://test.de/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
            ],
            'test-home-page-without-suffix-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/', '', 'frontend.home.page'),
            ],
            'test-home-page-with-de-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/de/', '/de', 'frontend.home.page'),
            ],
            'test-home-page-with-de-and-slash-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/de/', '/de/', 'frontend.home.page'),
            ],
            'test-home-page-with-de-without-slash-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/de/', 'de', 'frontend.home.page'),
            ],
            'test-home-page-with-null-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de/', null, 'frontend.home.page'),
            ],
            'test-navigation-page-with-de-network-path' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, "//test.de/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id]),
            ],

            'test-home-page-without-suffix-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/', '', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/de/', '/de', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-and-slash-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/de/', '/de/', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-without-slash-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/de/', 'de', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-null-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, 'http://test.de:8000/', null, 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-navigation-page-with-de-absolute-url-with-port' => [
                new UrlCase(UrlGeneratorInterface::ABSOLUTE_URL, "http://test.de:8000/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id], 'test.de:8000'),
            ],
            'test-home-page-without-suffix-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/', '', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/de/', '/de', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-and-slash-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/de/', '/de/', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-de-without-slash-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/de/', 'de', 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-home-page-with-null-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, '//test.de:8000/', null, 'frontend.home.page', [], 'test.de:8000'),
            ],
            'test-navigation-page-with-de-network-path-with-port' => [
                new UrlCase(UrlGeneratorInterface::NETWORK_PATH, "//test.de:8000/de/navigation/{$id}", '/de', 'frontend.navigation.page', ['navigationId' => $id], 'test.de:8000'),
            ],
        ];
    }
}

/**
 * @internal
 */
class UrlCase
{
    /**
     * @param array<string, string> $params
     */
    public function __construct(
        public int $type,
        public string $expected,
        public ?string $baseUrl,
        public string $route,
        public array $params = [],
        public string $host = 'test.de'
    ) {
    }
}

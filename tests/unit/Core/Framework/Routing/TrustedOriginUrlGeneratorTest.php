<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\TrustedOriginUrlGenerator;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TrustedOriginUrlGenerator::class)]
class TrustedOriginUrlGeneratorTest extends TestCase
{
    use EnvTestBehaviour;

    protected function setUp(): void
    {
        Request::setTrustedHosts([]);
        $this->setEnvVars([
            'APP_URL' => 'https://shop.example.com',
            'SHOPWARE_ADMINISTRATION_PATH_NAME' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
    }

    public function testGenerateUsesRequestHostWhenTrustedHostsAreConfigured(): void
    {
        Request::setTrustedHosts(['trusted.example.com']);
        $this->setEnvVars(['APP_URL' => 'not-a-url']);

        $router = $this->createRouter(new RequestContext('', 'GET', 'trusted.example.com', 'https'));

        static::assertSame(
            'https://trusted.example.com/api/oauth/sso/code?rdm=random',
            TrustedOriginUrlGenerator::generate($router, 'api.oauth.sso.code', ['rdm' => 'random'])
        );
    }

    public function testGenerateUsesAppUrlWhenNoTrustedHostsAreConfigured(): void
    {
        $this->setEnvVars(['APP_URL' => 'http://shop.example.com:8000/shop/']);

        $router = $this->createRouter(new RequestContext('', 'GET', 'other.example'));

        static::assertSame(
            'http://shop.example.com:8000/shop/api/oauth/sso/code?rdm=random',
            TrustedOriginUrlGenerator::generate($router, 'api.oauth.sso.code', ['rdm' => 'random'])
        );
        static::assertSame('other.example', $router->getContext()->getHost());
    }

    public function testGenerateRestoresRequestContextWhenRouteIsNotFound(): void
    {
        $router = $this->createRouter(new RequestContext('', 'GET', 'other.example'));

        try {
            TrustedOriginUrlGenerator::generate($router, 'unknown.route');
            static::fail('Expected ' . RouteNotFoundException::class);
        } catch (RouteNotFoundException) {
        }

        static::assertSame('other.example', $router->getContext()->getHost());
    }

    #[DataProvider('invalidAppUrlProvider')]
    public function testGenerateThrowsWhenAppUrlIsInvalid(string $appUrl): void
    {
        $this->setEnvVars(['APP_URL' => $appUrl]);

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        $this->expectExceptionObject(RoutingException::invalidAppUrl(rtrim($appUrl, '/')));

        TrustedOriginUrlGenerator::generate($router, 'api.oauth.sso.code');
    }

    public function testAdministrationUrlUsesAppUrlWithoutRouterWhenNoTrustedHostsAreConfigured(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        static::assertSame('https://shop.example.com/admin', TrustedOriginUrlGenerator::generateAdministrationUrl($router));
    }

    public function testAdministrationUrlUsesConfiguredPathName(): void
    {
        $this->setEnvVars([
            'APP_URL' => 'https://shop.example.com/',
            'SHOPWARE_ADMINISTRATION_PATH_NAME' => '/backoffice/',
        ]);

        static::assertSame(
            'https://shop.example.com/backoffice',
            TrustedOriginUrlGenerator::generateAdministrationUrl(static::createStub(RouterInterface::class))
        );
    }

    public function testAdministrationUrlUsesRouterWhenTrustedHostsAreConfigured(): void
    {
        Request::setTrustedHosts(['trusted.example.com']);
        $this->setEnvVars(['APP_URL' => 'not-a-url']);

        $router = $this->createRouter(new RequestContext('', 'GET', 'trusted.example.com', 'https'));

        static::assertSame('https://trusted.example.com/admin', TrustedOriginUrlGenerator::generateAdministrationUrl($router));
    }

    public function testAdministrationUrlFallsBackToAppUrlWhenRouteIsNotRegistered(): void
    {
        Request::setTrustedHosts(['trusted.example.com']);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willThrowException(new RouteNotFoundException());

        static::assertSame('https://shop.example.com/admin', TrustedOriginUrlGenerator::generateAdministrationUrl($router));
    }

    #[DataProvider('invalidAppUrlProvider')]
    public function testAdministrationUrlThrowsWhenAppUrlIsInvalid(string $appUrl): void
    {
        $this->setEnvVars(['APP_URL' => $appUrl]);

        $this->expectExceptionObject(RoutingException::invalidAppUrl(rtrim($appUrl, '/')));

        TrustedOriginUrlGenerator::generateAdministrationUrl(static::createStub(RouterInterface::class));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAppUrlProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'no scheme' => ['shop.example.com'];
        yield 'unsupported scheme' => ['ftp://shop.example.com'];
        yield 'javascript scheme' => ['javascript://shop.example.com'];
    }

    private function createRouter(RequestContext $context): Router
    {
        $routes = new RouteCollection();
        $routes->add('api.oauth.sso.code', new Route('/api/oauth/sso/code'));
        $routes->add('administration.index', new Route('/admin'));

        return new Router(new ClosureLoader(), static fn () => $routes, [], $context);
    }
}

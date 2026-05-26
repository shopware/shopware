<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;

/**
 * @internal
 */
class CacheResponseSubscriberTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    private const NO_STORE_ROUTES = [
        'frontend.account.order.page' => [],
        'frontend.account.order.single.page' => ['deepLinkCode' => 'abc'],
        'frontend.account.edit-order.page' => ['orderId' => 'abc'],
        'frontend.account.home.page' => [],
        'frontend.account.profile.page' => [],
        'frontend.account.address.page' => [],
        'frontend.account.address.create.page' => [],
        'frontend.account.address.edit.page' => ['addressId' => 'abc'],
        'frontend.account.login.page' => [],
        'frontend.account.guest.login.page' => [],
        'frontend.checkout.cart.page' => [],
        'frontend.checkout.confirm.page' => [],
        'frontend.checkout.finish.page' => [],
        'frontend.account.register.page' => [],
        'frontend.checkout.register.page' => [],
        'frontend.account.customer-group-registration.page' => ['customerGroupId' => 'abc'],
    ];

    /**
     * @param array<string, string> $routeParameters
     */
    #[DataProvider('dataProviderNoStoreRoutes')]
    public function testNoStoreHeaderPresent(string $routeName, array $routeParameters): void
    {
        $router = static::getContainer()->get('router');
        $route = $router->generate($routeName, $routeParameters);

        $browser = KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel(), true);
        $browser->request('GET', $_SERVER['APP_URL'] . $route);
        $response = $browser->getResponse();

        // see noCache() in CacheResponseSubscriber, no-store is only enforced when CACHE_REWORK and v6.8.0.0 are active
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'), 'Failed asserting route: ' . $routeName . ' with status code: ' . $response->getStatusCode());
        static::assertTrue($response->headers->hasCacheControlDirective('private'), 'Failed asserting route: ' . $routeName . ' with status code: ' . $response->getStatusCode());
        static::assertFalse($response->isCacheable());
    }

    /**
     * @return iterable<string, array{string, array<string>}>
     */
    public static function dataProviderNoStoreRoutes(): iterable
    {
        foreach (self::NO_STORE_ROUTES as $route => $parameters) {
            yield $route => [$route, $parameters];
        }
    }
}

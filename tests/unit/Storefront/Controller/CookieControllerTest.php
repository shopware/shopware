<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\AbstractCookieRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRouteResponse;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\CookieController;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CookieController::class)]
class CookieControllerTest extends TestCase
{
    public function testOffcanvasCallsRouteAndRendersTemplate(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->description = 'Test Group';

        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with(
                static::callback(static function (Request $req) {
                    return $req->query->getBoolean('translate') === false;
                }),
                $salesChannelContext
            )
            ->willReturn(new CookieRouteResponse($cookieGroups));

        $controller = new CookieControllerTestClass($cookieRoute);

        $response = $controller->offcanvas($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-configuration.html.twig', $controller->renderStorefrontView);
        static::assertArrayHasKey('cookieGroups', $controller->renderStorefrontParameters);
        static::assertNotEmpty($controller->renderStorefrontParameters['cookieGroups']);
        static::assertSame('noindex,follow', $response->headers->get('x-robots-tag'));
    }

    public function testPermissionCallsRouteAndRendersTemplate(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->description = 'Test Group';

        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with(
                static::callback(static function (Request $req) {
                    return $req->query->getBoolean('translate') === false;
                }),
                $salesChannelContext
            )
            ->willReturn(new CookieRouteResponse($cookieGroups));

        $controller = new CookieControllerTestClass($cookieRoute);

        $response = $controller->permission($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-permission.html.twig', $controller->renderStorefrontView);
        static::assertArrayHasKey('cookieGroups', $controller->renderStorefrontParameters);
        static::assertNotEmpty($controller->renderStorefrontParameters['cookieGroups']);
        static::assertSame('noindex,follow', $response->headers->get('x-robots-tag'));
    }

    public function testHandlesExceptionFromCookieRoute(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with(
                static::callback(static function (Request $req) {
                    return $req->query->getBoolean('translate') === false;
                }),
                $salesChannelContext
            );

        $controller = new CookieControllerTestClass($cookieRoute);

        $controller->offcanvas($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-configuration.html.twig', $controller->renderStorefrontView);
        static::assertArrayHasKey('cookieGroups', $controller->renderStorefrontParameters);
        static::assertEmpty($controller->renderStorefrontParameters['cookieGroups']);
    }

    public function testTransformCookieGroupForTwigSetsDefaultValues(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        // Create a cookie group without setting snippet values to test default handling
        $cookieGroup = new CookieGroup('test.group');
        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse($cookieGroups));

        $controller = new CookieControllerTestClass($cookieRoute);

        $controller->offcanvas($request, $salesChannelContext);

        $transformedGroups = $controller->renderStorefrontParameters['cookieGroups'];
        static::assertInstanceOf(CookieGroupCollection::class, $transformedGroups);
        static::assertNotEmpty($transformedGroups);

        // Check that default values are set
        $group = $transformedGroups->first();
        static::assertNotNull($group);

        static::assertObjectHasProperty('name', $group);
        static::assertObjectNotHasProperty('snippet_name', $group);
        static::assertObjectHasProperty('description', $group);
        static::assertObjectNotHasProperty('snippet_description', $group);
        static::assertObjectHasProperty('cookie', $group);
        static::assertObjectHasProperty('value', $group);
        static::assertObjectHasProperty('expiration', $group);
    }
}

/**
 * @internal
 */
class CookieControllerTestClass extends CookieController
{
    use StorefrontControllerMockTrait;
}

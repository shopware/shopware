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
use Symfony\Component\HttpFoundation\JsonResponse;
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
            ->with($request, $salesChannelContext)
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash'));

        $controller = new CookieControllerTestClass($cookieRoute);

        $response = $controller->offcanvas($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-configuration.html.twig', $controller->renderStorefrontView);
        static::assertArrayHasKey('cookieGroups', $controller->renderStorefrontParameters);
        static::assertNotEmpty($controller->renderStorefrontParameters['cookieGroups']);
        static::assertSame('noindex,follow', $response->headers->get('x-robots-tag'));
    }

    public function testOffcanvasThrowsExceptionWhenCookieRouteFails(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with($request, $salesChannelContext)
            ->willThrowException(new \RuntimeException('Cookie route failed'));

        $controller = new CookieControllerTestClass($cookieRoute);

        $this->expectExceptionObject(new \RuntimeException('Cookie route failed'));

        $controller->offcanvas($request, $salesChannelContext);
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
            ->with($request, $salesChannelContext)
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash'));

        $controller = new CookieControllerTestClass($cookieRoute);

        $response = $controller->permission($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-permission.html.twig', $controller->renderStorefrontView);
        static::assertArrayHasKey('cookieGroups', $controller->renderStorefrontParameters);
        static::assertNotEmpty($controller->renderStorefrontParameters['cookieGroups']);
        static::assertSame('noindex,follow', $response->headers->get('x-robots-tag'));
    }

    public function testOffcanvasPassesCookieGroupsDirectlyToTemplate(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        // Create a cookie group to verify it gets passed through unchanged
        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->description = 'Test description';
        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash'));

        $controller = new CookieControllerTestClass($cookieRoute);

        $controller->offcanvas($request, $salesChannelContext);

        // Verify the exact same collection is passed to the template (no transformation)
        $passedGroups = $controller->renderStorefrontParameters['cookieGroups'];
        static::assertSame($cookieGroups, $passedGroups);
        static::assertSame($cookieGroup, $passedGroups->first());
    }

    public function testCookieConsentOffcanvasWithDefaults(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $controller = new CookieControllerTestClass($cookieRoute);

        $response = $controller->cookieConsentOffcanvas($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-consent-offcanvas.html.twig', $controller->renderStorefrontView);
        static::assertArrayHasKey('featureName', $controller->renderStorefrontParameters);
        static::assertArrayHasKey('cookieName', $controller->renderStorefrontParameters);
        static::assertSame('wishlist', $controller->renderStorefrontParameters['featureName']);
        static::assertSame('wishlist-enabled', $controller->renderStorefrontParameters['cookieName']);
    }

    public function testCookieConsentOffcanvasWithCustomParameters(): void
    {
        $request = new Request(['featureName' => 'customFeature', 'cookieName' => 'custom-cookie']);
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $controller = new CookieControllerTestClass($cookieRoute);

        $response = $controller->cookieConsentOffcanvas($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-consent-offcanvas.html.twig', $controller->renderStorefrontView);
        static::assertArrayHasKey('featureName', $controller->renderStorefrontParameters);
        static::assertArrayHasKey('cookieName', $controller->renderStorefrontParameters);
        static::assertSame('customFeature', $controller->renderStorefrontParameters['featureName']);
        static::assertSame('custom-cookie', $controller->renderStorefrontParameters['cookieName']);
    }

    public function testGroupsCallsCookieRouteAndReturnsData(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->description = 'Test Group';
        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with($request, $salesChannelContext)
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash'));

        $controller = new CookieControllerTestClass($cookieRoute);

        // Override the json method to capture the data being passed to it
        $jsonData = null;
        $controller->jsonCallback = function ($data) use (&$jsonData) {
            $jsonData = $data;

            return new JsonResponse($data);
        };

        $response = $controller->groups($request, $salesChannelContext);

        static::assertNotNull($jsonData);
        static::assertArrayHasKey('elements', $jsonData);
        static::assertArrayHasKey('hash', $jsonData);
        static::assertSame('test-hash', $jsonData['hash']);
        static::assertSame($cookieGroups, $jsonData['elements']);
    }

    public function testGroupsThrowsExceptionWhenCookieRouteFails(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with($request, $salesChannelContext)
            ->willThrowException(new \RuntimeException('Cookie route failed'));

        $controller = new CookieControllerTestClass($cookieRoute);

        $this->expectExceptionObject(new \RuntimeException('Cookie route failed'));

        $controller->groups($request, $salesChannelContext);
    }
}

/**
 * @internal
 */
class CookieControllerTestClass extends CookieController
{
    use StorefrontControllerMockTrait;

    /**
     * @var callable|null
     */
    public $jsonCallback;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $context
     */
    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        if ($this->jsonCallback !== null) {
            if (\is_object($data) && method_exists($data, 'all')) {
                $data = $data->all();
            }

            return ($this->jsonCallback)($data);
        }

        return new JsonResponse($data, $status, $headers);
    }
}

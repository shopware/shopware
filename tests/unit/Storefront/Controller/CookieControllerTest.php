<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\SalesChannel\AbstractCookieRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogPayload;
use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRouteResponse;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\CookieController;
use Shopware\Tests\Unit\Storefront\Controller\Stub\CookieControllerStub;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
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
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash', 'test-language-id'));

        $controller = new CookieControllerStub($cookieRoute, static::createStub(CookieConsentLogRoute::class));

        $response = $controller->offcanvas($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-configuration.html.twig', $controller->recorder()->renderStorefrontView);
        static::assertArrayHasKey('cookieGroups', $controller->recorder()->renderStorefrontParameters);
        static::assertNotEmpty($controller->recorder()->renderStorefrontParameters['cookieGroups']);
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

        $controller = new CookieControllerStub($cookieRoute, static::createStub(CookieConsentLogRoute::class));

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
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash', 'test-language-id'));

        $controller = new CookieControllerStub($cookieRoute, static::createStub(CookieConsentLogRoute::class));

        $response = $controller->permission($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-permission.html.twig', $controller->recorder()->renderStorefrontView);
        static::assertArrayHasKey('cookieGroups', $controller->recorder()->renderStorefrontParameters);
        static::assertNotEmpty($controller->recorder()->renderStorefrontParameters['cookieGroups']);
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

        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash', 'test-language-id'));

        $controller = new CookieControllerStub($cookieRoute, static::createStub(CookieConsentLogRoute::class));

        $controller->offcanvas($request, $salesChannelContext);

        // Verify the exact same collection is passed to the template (no transformation)
        $passedGroups = $controller->recorder()->renderStorefrontParameters['cookieGroups'];
        static::assertSame($cookieGroups, $passedGroups);
        static::assertSame($cookieGroup, $passedGroups->first());
    }

    public function testCookieConsentOffcanvasWithDefaults(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $controller = new CookieControllerStub($cookieRoute, static::createStub(CookieConsentLogRoute::class));

        $response = $controller->cookieConsentOffcanvas($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-consent-offcanvas.html.twig', $controller->recorder()->renderStorefrontView);
        static::assertArrayHasKey('featureName', $controller->recorder()->renderStorefrontParameters);
        static::assertArrayHasKey('cookieName', $controller->recorder()->renderStorefrontParameters);
        static::assertSame('wishlist', $controller->recorder()->renderStorefrontParameters['featureName']);
        static::assertSame('wishlist-enabled', $controller->recorder()->renderStorefrontParameters['cookieName']);
    }

    public function testCookieConsentOffcanvasWithCustomParameters(): void
    {
        $request = new Request(['featureName' => 'customFeature', 'cookieName' => 'custom-cookie']);
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $controller = new CookieControllerStub($cookieRoute, static::createStub(CookieConsentLogRoute::class));

        $response = $controller->cookieConsentOffcanvas($request, $salesChannelContext);

        static::assertSame('@Storefront/storefront/layout/cookie/cookie-consent-offcanvas.html.twig', $controller->recorder()->renderStorefrontView);
        static::assertArrayHasKey('featureName', $controller->recorder()->renderStorefrontParameters);
        static::assertArrayHasKey('cookieName', $controller->recorder()->renderStorefrontParameters);
        static::assertSame('customFeature', $controller->recorder()->renderStorefrontParameters['featureName']);
        static::assertSame('custom-cookie', $controller->recorder()->renderStorefrontParameters['cookieName']);
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
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash', 'test-language-id'));

        $controller = new CookieControllerStub($cookieRoute, static::createStub(CookieConsentLogRoute::class));

        // Override the json method to capture the data being passed to it
        $jsonData = null;
        $controller->jsonCallback = static function ($data) use (&$jsonData) {
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

    public function testLogConsentDelegatesToConsentLogRoute(): void
    {
        $payload = new CookieConsentLogPayload('visitor-a', CookieConsentAction::ACCEPT_ALL);
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $consentLogRoute = $this->createMock(CookieConsentLogRoute::class);
        $consentLogRoute->expects($this->once())
            ->method('log')
            ->with($payload, $request, $salesChannelContext)
            ->willReturn(new NoContentResponse());

        $controller = new CookieControllerStub(static::createStub(AbstractCookieRoute::class), $consentLogRoute);

        $response = $controller->logConsent($payload, $request, $salesChannelContext);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
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

        $controller = new CookieControllerStub($cookieRoute, static::createStub(CookieConsentLogRoute::class));

        $this->expectExceptionObject(new \RuntimeException('Cookie route failed'));

        $controller->groups($request, $salesChannelContext);
    }
}

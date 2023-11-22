<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\EventListener\Authentication\SalesChannelAuthenticationListener;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SalesChannelAuthenticationListener::class)]
#[CoversClass(MaintenanceModeResolver::class)]
class SalesChannelAuthenticationListenerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const MAINTENANCE_ALLOWED_IPS = ['192.168.0.2', '192.168.0.1', '192.168.0.3'];

    public function testInactiveSalesChannel(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => false]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default');

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_PRECONDITION_FAILED,
            ApiException::salesChannelNotFound()->getErrorCode()
        );
    }

    public function testActiveSalesChannel(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default');

        $this->assertResponseSuccess($browser);
    }

    public function testMaintenanceSalesChannel(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => true, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default');

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_SERVICE_UNAVAILABLE,
            ApiException::API_SALES_CHANNEL_MAINTENANCE_MODE
        );
    }

    public function testInactiveAndMaintenanceSalesChannel(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => false, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default');

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_PRECONDITION_FAILED,
            ApiException::salesChannelNotFound()->getErrorCode()
        );
    }

    public function testMaintenanceSalesChannelAndClientInAllowedIps(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => true, 'maintenance' => true, 'maintenanceIpWhitelist' => self::MAINTENANCE_ALLOWED_IPS]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default', server: ['REMOTE_ADDR' => '192.168.0.1']);

        $this->assertResponseSuccess($browser);
    }

    public function testMaintenanceSalesChannelAndClientNotInAllowedIps(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => true, 'maintenance' => true, 'maintenanceIpWhitelist' => self::MAINTENANCE_ALLOWED_IPS]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/default', server: ['REMOTE_ADDR' => '192.168.0.4']);

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_SERVICE_UNAVAILABLE,
            ApiException::API_SALES_CHANNEL_MAINTENANCE_MODE
        );
    }

    public function testMaintenanceSalesChannelWithMaintenanceAllowedRoute(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => true, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/maintenance-allowed');

        $this->assertResponseSuccess($browser);
    }

    public function testMaintenanceSalesChannelWithMaintenanceDisallowedRoute(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => true, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/maintenance-disallowed');

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_SERVICE_UNAVAILABLE,
            ApiException::API_SALES_CHANNEL_MAINTENANCE_MODE
        );
    }

    public function testMaintenanceSalesChannelWithMaintenanceDisallowedRouteAndClientNotInAllowedIps(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => true, 'maintenance' => true, 'maintenanceIpWhitelist' => self::MAINTENANCE_ALLOWED_IPS]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/maintenance-disallowed', server: ['REMOTE_ADDR' => '192.168.0.1']);

        $this->assertResponseSuccess($browser);
    }

    public function testMaintenanceSalesChannelWithMaintenanceDisallowedRouteAndClientInAllowedIps(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => true, 'maintenance' => true, 'maintenanceIpWhitelist' => self::MAINTENANCE_ALLOWED_IPS]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/maintenance-disallowed', server: ['REMOTE_ADDR' => '192.168.0.4']);

        $this->assertExceptionResponse(
            $browser,
            Response::HTTP_SERVICE_UNAVAILABLE,
            ApiException::API_SALES_CHANNEL_MAINTENANCE_MODE
        );
    }

    public function testRouteWithoutAuthRequiredIgnoresActiveFlag(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => false]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/no-auth-required');

        $this->assertResponseSuccess($browser);
    }

    public function testRouteWithoutAuthRequiredIgnoreMaintenanceModeFlag(): void
    {
        $browser = $this->createSalesChannelBrowser(salesChannelOverrides: ['active' => true, 'maintenance' => true]);
        $browser->request(Request::METHOD_GET, '/store-api/test/sales-channel-authentication-listener/no-auth-required');

        $this->assertResponseSuccess($browser);
    }

    private function assertExceptionResponse(KernelBrowser $browser, int $statusCode, string $errorCode): void
    {
        $response = $browser->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame($statusCode, $response->getStatusCode(), (string) $response->getContent());

        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('errors', $data);
        static::assertCount(1, $data['errors'] ?? []);

        $error = $data['errors'][0];

        static::assertSame((string) $statusCode, $error['status']);
        static::assertSame($errorCode, $error['code']);
    }

    private function assertResponseSuccess(KernelBrowser $browser): void
    {
        $response = $browser->getResponse();
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame('', $content);
    }
}

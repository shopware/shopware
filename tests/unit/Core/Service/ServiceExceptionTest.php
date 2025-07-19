<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\ServiceException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[CoversClass(ServiceException::class)]
class ServiceExceptionTest extends TestCase
{
    public function testNotFound(): void
    {
        $e = ServiceException::notFound('name', 'MyCoolService');

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(ServiceException::NOT_FOUND, $e->getErrorCode());
        static::assertSame('Could not find service with name "MyCoolService"', $e->getMessage());
    }

    public function testUpdateRequiresAdminApiSource(): void
    {
        $source = new ShopApiSource(Uuid::randomHex());
        $e = ServiceException::updateRequiresAdminApiSource($source);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_UPDATE_REQUIRES_ADMIN_API_SOURCE, $e->getErrorCode());
        static::assertSame('Updating a service requires Shopware\Core\Framework\Api\Context\AdminApiSource, but got Shopware\Core\Framework\Api\Context\ShopApiSource', $e->getMessage());
    }

    public function testUpdateRequiresIntegration(): void
    {
        $e = ServiceException::updateRequiresIntegration();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_UPDATE_REQUIRES_INTEGRATION, $e->getErrorCode());
        static::assertSame('Updating a service requires an integration', $e->getMessage());
    }

    public function testRequestFailed(): void
    {
        $response = static::createMock(ResponseInterface::class);
        $response->expects($this->any())->method('getStatusCode')->willReturn(Response::HTTP_NOT_FOUND);

        $e = ServiceException::requestFailed($response);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertSame('Error performing request. Response code: 404', $e->getMessage());
    }

    public function testRequestFailedWithErrors(): void
    {
        $response = static::createMock(ResponseInterface::class);
        $response->expects($this->any())->method('getStatusCode')->willReturn(Response::HTTP_NOT_FOUND);
        $response->expects($this->once())->method('toArray')->with(false)->willReturn(['errors' => ['Error 1', 'Error 2']]);

        $e = ServiceException::requestFailed($response);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertSame('Error performing request. Response code: 404. Errors: ["Error 1","Error 2"]', $e->getMessage());
    }

    public function testRequestTransportError(): void
    {
        $e = ServiceException::requestTransportError();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertSame('Error performing request', $e->getMessage());
    }

    public function testRequestTransportErrorWithPrevious(): void
    {
        $previous = new \Exception('Some error');
        $e = ServiceException::requestTransportError($previous);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertSame('Error performing request. Error: Some error', $e->getMessage());
        static::assertSame($previous, $e->getPrevious());
    }

    public function testMissingAppVersionInfo(): void
    {
        $e = ServiceException::missingAppVersionInfo();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_MISSING_APP_VERSION_INFO, $e->getErrorCode());
        static::assertSame('Error downloading app. The version information was missing.', $e->getMessage());
    }

    public function testCannotWriteAppToDestination(): void
    {
        $e = ServiceException::cannotWriteAppToDestination('/some/path');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_CANNOT_WRITE_APP, $e->getErrorCode());
        static::assertSame('Error writing app zip to file "/some/path"', $e->getMessage());
    }

    public function testInvalidPermissionsRevisionFormat(): void
    {
        $e = ServiceException::invalidPermissionsRevisionFormat('foobar');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::INVALID_PERMISSIONS_REVISION_FORMAT, $e->getErrorCode());
        static::assertSame('The provided permissions revision "foobar" is not in the correct format Y-m-d.', $e->getMessage());
    }

    public function testToggleActionNotAllowed(): void
    {
        $e = ServiceException::toggleActionNotAllowed();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_TOGGLE_ACTION_NOT_ALLOWED, $e->getErrorCode());
        static::assertSame('Service is not allowed to toggle itself.', $e->getMessage());
    }

    public function testMissingAppSecretInfo(): void
    {
        $e = ServiceException::missingAppSecretInfo('app-123');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_MISSING_APP_SECRET_INFO, $e->getErrorCode());
        static::assertSame('Error creating client. The app secret information was missing. App ID: "app-123"', $e->getMessage());
    }

    public function testScheduledTaskNotRegistered(): void
    {
        $e = ServiceException::scheduledTaskNotRegistered();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(ServiceException::SCHEDULED_TASK_NOT_REGISTERED, $e->getErrorCode());
        static::assertSame('Could not queue task "services.install" because it is not registered.', $e->getMessage());
    }

    public function testInvalidServicesState(): void
    {
        $e = ServiceException::invalidServicesState();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_INVALID_SERVICES_STATE, $e->getErrorCode());
        static::assertSame('The services are in an invalid state. Cannot start if the consent is not given.', $e->getMessage());
    }

    public function testServiceNotInstalled(): void
    {
        $e = ServiceException::serviceNotInstalled('MyService');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICES_NOT_INSTALLED, $e->getErrorCode());
        static::assertSame('The service is not installed.', $e->getMessage());
    }

    public function testConsentSaveFailed(): void
    {
        $e = ServiceException::consentSaveFailed('Network timeout');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_REQUEST_FAILED, $e->getErrorCode());
        static::assertSame('Could not save consent: Network timeout', $e->getMessage());
    }

    public function testConsentRevokeFailed(): void
    {
        $e = ServiceException::consentRevokeFailed('Server error');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_REQUEST_FAILED, $e->getErrorCode());
        static::assertSame('Could not revoke consent: Server error', $e->getMessage());
    }

    public function testNoCurrentPermissionsConsent(): void
    {
        $e = ServiceException::noCurrentPermissionsConsent();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::NO_CURRENT_PERMISSIONS_CONSENT, $e->getErrorCode());
        static::assertSame('No current permissions consent found.', $e->getMessage());
    }

    public function testInvalidPermissionsContext(): void
    {
        $e = ServiceException::invalidPermissionsContext();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::SERVICE_REQUEST_FAILED, $e->getErrorCode());
        static::assertSame('This action is only allowed from Admins.', $e->getMessage());
    }

    public function testInvalidPermissionConsentFormat(): void
    {
        $invalidJson = ['invalid' => 'data'];
        $e = ServiceException::invalidPermissionConsentFormat($invalidJson);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ServiceException::INVALID_PERMISSIONS_REVISION_FORMAT, $e->getErrorCode());
        static::assertSame('The saved permissions consent is not in a valid format.', $e->getMessage());
    }
}

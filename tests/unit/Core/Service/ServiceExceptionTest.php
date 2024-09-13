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

        static::assertEquals(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertEquals(ServiceException::NOT_FOUND, $e->getErrorCode());
        static::assertEquals('Could not find service with name "MyCoolService"', $e->getMessage());
    }

    public function testUpdateRequiresAdminApiSource(): void
    {
        $source = new ShopApiSource(Uuid::randomHex());
        $e = ServiceException::updateRequiresAdminApiSource($source);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServiceException::SERVICE_UPDATE_REQUIRES_ADMIN_API_SOURCE, $e->getErrorCode());
        static::assertEquals('Updating a service requires Shopware\Core\Framework\Api\Context\AdminApiSource, but got Shopware\Core\Framework\Api\Context\ShopApiSource', $e->getMessage());
    }

    public function testUpdateRequiresIntegration(): void
    {
        $e = ServiceException::updateRequiresIntegration();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServiceException::SERVICE_UPDATE_REQUIRES_INTEGRATION, $e->getErrorCode());
        static::assertEquals('Updating a service requires an integration', $e->getMessage());
    }

    public function testRequestFailed(): void
    {
        $response = static::createMock(ResponseInterface::class);
        $response->expects(static::once())->method('getStatusCode')->willReturn(Response::HTTP_NOT_FOUND);

        $e = ServiceException::requestFailed($response);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServiceException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertEquals('Error performing request. Response code: 404', $e->getMessage());
    }

    public function testRequestFailedWithErrors(): void
    {
        $response = static::createMock(ResponseInterface::class);
        $response->expects(static::once())->method('getStatusCode')->willReturn(Response::HTTP_NOT_FOUND);
        $response->expects(static::once())->method('toArray')->with(false)->willReturn(['errors' => ['Error 1', 'Error 2']]);

        $e = ServiceException::requestFailed($response);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServiceException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertEquals('Error performing request. Response code: 404. Errors: ["Error 1","Error 2"]', $e->getMessage());
    }

    public function testRequestTransportError(): void
    {
        $e = ServiceException::requestTransportError();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServiceException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertEquals('Error performing request', $e->getMessage());
    }

    public function testRequestTransportErrorWithPrevious(): void
    {
        $previous = new \Exception('Some error');
        $e = ServiceException::requestTransportError($previous);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServiceException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertEquals('Error performing request. Error: Some error', $e->getMessage());
        static::assertEquals($previous, $e->getPrevious());
    }

    public function testMissingAppVersionInfo(): void
    {
        $e = ServiceException::missingAppVersionInfo();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServiceException::SERVICE_MISSING_APP_VERSION_INFO, $e->getErrorCode());
        static::assertEquals('Error downloading app. The version information was missing.', $e->getMessage());
    }

    public function testCannotWriteAppToDestination(): void
    {
        $e = ServiceException::cannotWriteAppToDestination('/some/path');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServiceException::SERVICE_CANNOT_WRITE_APP, $e->getErrorCode());
        static::assertEquals('Error writing app zip to file "/some/path"', $e->getMessage());
    }
}

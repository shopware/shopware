<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Services\ServicesException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ServicesException::class)]
class ServicesExceptionTest extends TestCase
{
    public function testNotFound(): void
    {
        $e = ServicesException::notFound('name', 'MyCoolService');

        static::assertEquals(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertEquals(ServicesException::NOT_FOUND, $e->getErrorCode());
        static::assertEquals('Could not find service with name "MyCoolService"', $e->getMessage());
    }

    public function testUpdateRequiresAdminApiSource(): void
    {
        $source = new ShopApiSource(Uuid::randomHex());
        $e = ServicesException::updateRequiresAdminApiSource($source);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServicesException::SERVICE_UPDATE_REQUIRES_ADMIN_API_SOURCE, $e->getErrorCode());
        static::assertEquals('Updating a service requires Shopware\Core\Framework\Api\Context\AdminApiSource, but got Shopware\Core\Framework\Api\Context\ShopApiSource', $e->getMessage());
    }

    public function testUpdateRequiresIntegration(): void
    {
        $e = ServicesException::updateRequiresIntegration();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServicesException::SERVICE_UPDATE_REQUIRES_INTEGRATION, $e->getErrorCode());
        static::assertEquals('Updating a service requires an integration', $e->getMessage());
    }

    public function testRequestFailed(): void
    {
        $e = ServicesException::requestFailed(404);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServicesException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertEquals('Error performing request. Response code: 404', $e->getMessage());
    }

    public function testRequestTransportError(): void
    {
        $e = ServicesException::requestTransportError();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServicesException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertEquals('Error performing request', $e->getMessage());
    }

    public function testRequestTransportErrorWithPrevious(): void
    {
        $previous = new \Exception('Some error');
        $e = ServicesException::requestTransportError($previous);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServicesException::SERVICE_REQUEST_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertEquals('Error performing request. Error: Some error', $e->getMessage());
        static::assertEquals($previous, $e->getPrevious());
    }

    public function testMissingAppVersionInfo(): void
    {
        $e = ServicesException::missingAppVersionInfo();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServicesException::SERVICE_MISSING_APP_VERSION_INFO, $e->getErrorCode());
        static::assertEquals('Error downloading app. The version information was missing.', $e->getMessage());
    }

    public function testCannotWriteAppToDestination(): void
    {
        $e = ServicesException::cannotWriteAppToDestination('/some/path');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals(ServicesException::SERVICE_CANNOT_WRITE_APP, $e->getErrorCode());
        static::assertEquals('Error writing app zip to file "/some/path"', $e->getMessage());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Increment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(IncrementException::class)]
class IncrementExceptionTest extends TestCase
{
    public function testKeyParameterIsMissing(): void
    {
        $exception = IncrementException::keyParameterIsMissing();
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('Parameter "key" is missing.', $exception->getMessage());
    }

    public function testClusterParameterIsMissing(): void
    {
        $exception = IncrementException::clusterParameterIsMissing();
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('Parameter "cluster" is missing.', $exception->getMessage());
    }

    public function testGatewayNotFound(): void
    {
        $pool = 'test_pool';
        $exception = IncrementException::gatewayNotFound($pool);
        static::assertInstanceOf(IncrementGatewayNotFoundException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('Increment gateway for pool "test_pool" was not found.', $exception->getMessage());
    }

    public function testWrongGatewayType(): void
    {
        $pool = 'test_pool';
        $exception = IncrementException::wrongGatewayType($pool);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('shopware.increment.gateway type of test_pool pool must be a string', $exception->getMessage());
    }

    public function testGatewayServiceNotFound(): void
    {
        $type = 'test_type';
        $pool = 'test_pool';
        $serviceId = 'test_service_id';
        $exception = IncrementException::gatewayServiceNotFound($type, $pool, $serviceId);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Can not find increment gateway for configured type test_type of pool test_pool, expected service id test_service_id can not be found', $exception->getMessage());
    }

    public function testWrongGatewayClass(): void
    {
        $serviceId = 'test_service_id';
        $requiredClass = 'test_required_class';
        $exception = IncrementException::wrongGatewayClass($serviceId, $requiredClass);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('Increment gateway with id test_service_id, expected service instance of test_required_class', $exception->getMessage());
    }
}

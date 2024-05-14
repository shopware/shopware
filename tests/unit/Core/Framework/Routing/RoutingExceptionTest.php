<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(RoutingException::class)]
class RoutingExceptionTest extends TestCase
{
    public function testInvalidRequestParameter(): void
    {
        $e = RoutingException::invalidRequestParameter('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(RoutingException::INVALID_REQUEST_PARAMETER_CODE, $e->getErrorCode());
    }

    public function testMissingRequestParameter(): void
    {
        $e = RoutingException::missingRequestParameter('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(RoutingException::MISSING_REQUEST_PARAMETER_CODE, $e->getErrorCode());
    }

    public function testLanguageNotFound(): void
    {
        $e = RoutingException::languageNotFound('foo');

        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $e->getStatusCode());
        static::assertSame(RoutingException::LANGUAGE_NOT_FOUND, $e->getErrorCode());
    }

    public function testAppIntegrationNotFound(): void
    {
        $e = RoutingException::appIntegrationNotFound('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(RoutingException::APP_INTEGRATION_NOT_FOUND, $e->getErrorCode());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \Shopware\Core\Framework\Routing\RoutingException
 *
 * @internal
 */
#[Package('core')]
class RoutingExceptionTest extends TestCase
{
    public function testInvalidRequestParameter(): void
    {
        $e = RoutingException::invalidRequestParameter('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(RoutingException::INVALID_REQUEST_PARAMETER_CODE, $e->getErrorCode());
    }

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0 - will be removed
     */
    public function testInvalidRequestParameterLegacy(): void
    {
        $e = RoutingException::invalidRequestParameter('foo');

        static::assertInstanceOf(InvalidRequestParameterException::class, $e);
    }

    public function testMissingRequestParameter(): void
    {
        $e = RoutingException::missingRequestParameter('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(RoutingException::MISSING_REQUEST_PARAMETER_CODE, $e->getErrorCode());
    }

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0 - will be removed
     */
    public function testMissingRequestParameterLegacy(): void
    {
        $e = RoutingException::missingRequestParameter('foo');

        static::assertInstanceOf(MissingRequestParameterException::class, $e);
    }

    public function testLanguageNotFound(): void
    {
        $e = RoutingException::languageNotFound('foo');

        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $e->getStatusCode());
        static::assertSame(RoutingException::LANGUAGE_NOT_FOUND, $e->getErrorCode());
    }

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @deprecated tag:v6.6.0.0 - will be removed
     */
    public function testLanguageNotFoundLegacy(): void
    {
        $e = RoutingException::languageNotFound('foo');

        static::assertInstanceOf(LanguageNotFoundException::class, $e);
    }

    public function testAppIntegrationNotFound(): void
    {
        $e = RoutingException::appIntegrationNotFound('foo');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(RoutingException::APP_INTEGRATION_NOT_FOUND, $e->getErrorCode());
    }
}

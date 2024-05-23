<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Gateway\CheckoutGatewayException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CheckoutGatewayException::class)]
#[Package('checkout')]
class CheckoutGatewayExceptionTest extends TestCase
{
    public function testCanBeThrown(): void
    {
        $e = new CheckoutGatewayException(1, 'CHECKOUT_TEST', 'test: {{ foo }}', ['foo' => 'bar']);

        static::assertSame(1, $e->getStatusCode());
        static::assertSame('test: bar', $e->getMessage());
        static::assertSame('CHECKOUT_TEST', $e->getErrorCode());
        static::assertSame(['foo' => 'bar'], $e->getParameters());

        static::expectException(CheckoutGatewayException::class);

        throw $e;
    }

    public function testEmptyAppResponse(): void
    {
        $exception = CheckoutGatewayException::emptyAppResponse('foo');

        static::assertSame('App "foo" did not provide checkout gateway response', $exception->getMessage());
        static::assertSame('CHECKOUT_GATEWAY__EMPTY_APP_RESPONSE', $exception->getErrorCode());
        static::assertSame(['app' => 'foo'], $exception->getParameters());
    }

    public function testPayloadInvalid(): void
    {
        $exception = CheckoutGatewayException::payloadInvalid('test');

        static::assertSame('Payload invalid for command "test"', $exception->getMessage());
        static::assertSame('CHECKOUT_GATEWAY__PAYLOAD_INVALID', $exception->getErrorCode());
        static::assertSame(['command' => 'test'], $exception->getParameters());
    }

    public function testPayloadInvalidWithoutCommandKey(): void
    {
        $exception = CheckoutGatewayException::payloadInvalid();

        static::assertSame('Payload invalid for command', $exception->getMessage());
        static::assertSame('CHECKOUT_GATEWAY__PAYLOAD_INVALID', $exception->getErrorCode());
        static::assertSame(['command' => null], $exception->getParameters());
    }

    public function testHandlerNotFound(): void
    {
        $exception = CheckoutGatewayException::handlerNotFound('test');

        static::assertSame('Handler not found for command "test"', $exception->getMessage());
        static::assertSame('CHECKOUT_GATEWAY__HANDLER_NOT_FOUND', $exception->getErrorCode());
        static::assertSame(['key' => 'test'], $exception->getParameters());
    }

    public function testHandlerException(): void
    {
        $exception = CheckoutGatewayException::handlerException('test', ['foo' => 'bar']);

        static::assertSame('test', $exception->getMessage());
        static::assertSame('CHECKOUT_GATEWAY__HANDLER_EXCEPTION', $exception->getErrorCode());
        static::assertSame(['foo' => 'bar'], $exception->getParameters());
    }
}

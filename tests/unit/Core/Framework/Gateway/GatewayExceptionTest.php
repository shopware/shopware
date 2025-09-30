<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Gateway\GatewayException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GatewayException::class)]
class GatewayExceptionTest extends TestCase
{
    public function testCanBeThrown(): void
    {
        $e = new GatewayException(1, 'CONTEXT_TEST', 'test: {{ foo }}', ['foo' => 'bar']);

        static::assertSame(1, $e->getStatusCode());
        static::assertSame('test: bar', $e->getMessage());
        static::assertSame('CONTEXT_TEST', $e->getErrorCode());
        static::assertSame(['foo' => 'bar'], $e->getParameters());

        static::expectException(GatewayException::class);

        throw $e;
    }

    public function testEmptyAppResponse(): void
    {
        $exception = GatewayException::emptyAppResponse('foo');

        static::assertSame('App "foo" did not provide context gateway response', $exception->getMessage());
        static::assertSame('CONTEXT_GATEWAY__EMPTY_APP_RESPONSE', $exception->getErrorCode());
        static::assertSame(['app' => 'foo'], $exception->getParameters());
    }

    public function testPayloadInvalid(): void
    {
        $exception = GatewayException::payloadInvalid('test');

        static::assertSame('Payload invalid for command "test"', $exception->getMessage());
        static::assertSame('CONTEXT_GATEWAY__PAYLOAD_INVALID', $exception->getErrorCode());
        static::assertSame(['command' => 'test'], $exception->getParameters());
    }

    public function testPayloadInvalidWithoutCommandKey(): void
    {
        $exception = GatewayException::payloadInvalid();

        static::assertSame('Payload invalid for command', $exception->getMessage());
        static::assertSame('CONTEXT_GATEWAY__PAYLOAD_INVALID', $exception->getErrorCode());
        static::assertSame(['command' => null], $exception->getParameters());
    }

    public function testHandlerNotFound(): void
    {
        $exception = GatewayException::handlerNotFound('test');

        static::assertSame('Handler not found for command "test"', $exception->getMessage());
        static::assertSame('CONTEXT_GATEWAY__HANDLER_NOT_FOUND', $exception->getErrorCode());
        static::assertSame(['key' => 'test'], $exception->getParameters());
    }

    public function testHandlerException(): void
    {
        $exception = GatewayException::handlerException('test', ['foo' => 'bar']);

        static::assertSame('test', $exception->getMessage());
        static::assertSame('CONTEXT_GATEWAY__HANDLER_EXCEPTION', $exception->getErrorCode());
        static::assertSame(['foo' => 'bar'], $exception->getParameters());
    }

    public function testCommandValidationFailed(): void
    {
        $exception = GatewayException::commandValidationFailed('test', ['foo' => 'bar']);

        static::assertSame('test', $exception->getMessage());
        static::assertSame('CONTEXT_GATEWAY__COMMAND_VALIDATION_FAILED', $exception->getErrorCode());
        static::assertSame(['foo' => 'bar'], $exception->getParameters());
    }

    public function testCustomerMessage(): void
    {
        $exception = GatewayException::customerMessage('test');

        static::assertSame('test', $exception->getMessage());
        static::assertSame('CONTEXT_GATEWAY__CUSTOMER_MESSAGE', $exception->getErrorCode());
        static::assertSame([], $exception->getParameters());
    }
}

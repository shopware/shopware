<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Payload\AppPayloadStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AppPayloadStruct::class)]
class AppPayloadStructTest extends TestCase
{
    public function testConstructorAssignsTheRequestOptions(): void
    {
        $context = Context::createDefaultContext();

        $struct = new AppPayloadStruct([
            'app_request_context' => $context,
            'request_type' => ['app_secret' => 'secret', 'validated_response' => true],
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{"payload": true}',
        ]);

        static::assertSame($context, $struct->appRequestContext);
        static::assertSame(['app_secret' => 'secret', 'validated_response' => true], $struct->requestType);
        static::assertSame(['Content-Type' => 'application/json'], $struct->headers);
        static::assertSame('{"payload": true}', $struct->body);
        static::assertNull($struct->timeout);
    }

    public function testJsonSerializeUsesSnakeCaseKeys(): void
    {
        $struct = new AppPayloadStruct([
            'app_request_context' => Context::createDefaultContext(),
            'request_type' => ['app_secret' => 'secret', 'validated_response' => true],
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{}',
            'timeout' => 5,
        ]);

        $data = $struct->jsonSerialize();

        static::assertSame(
            ['app_request_context', 'request_type', 'headers', 'body', 'timeout'],
            array_keys($data)
        );
        static::assertSame(5, $data['timeout']);
    }
}

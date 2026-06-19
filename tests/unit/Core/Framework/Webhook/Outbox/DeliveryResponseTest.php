<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Outbox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Service\WebhookRequest;
use Shopware\Core\Framework\Webhook\Service\WebhookResult;

/**
 * @internal
 */
#[CoversClass(DeliveryResponse::class)]
class DeliveryResponseTest extends TestCase
{
    #[TestDox('from() serialises request and response when the result has a response')]
    public function testFromWithResponse(): void
    {
        $request = new WebhookRequest(
            static::createStub(RequestInterface::class),
            ['Content-Type' => 'application/json'],
            '{"event":"order.placed"}',
            1700000000,
        );
        $result = new WebhookResult(
            body: '{"ok":true}',
            statusCode: 200,
            reasonPhrase: 'OK',
            headers: ['X-Foo' => ['bar']],
            processingTimeSeconds: 5,
        );

        $response = DeliveryResponse::from($request, $result);

        static::assertSame(
            json_encode(['headers' => $request->headers, 'body' => $request->body]),
            $response->requestContent,
        );
        static::assertSame(
            json_encode(['headers' => $result->headers, 'body' => $result->body]),
            $response->responseContent,
        );
        static::assertSame(200, $response->responseStatusCode);
        static::assertSame('OK', $response->responseReasonPhrase);
        static::assertSame(5, $response->processingTimeSeconds);
    }

    #[TestDox('from() leaves response content null when the result has no response')]
    public function testFromWithoutResponse(): void
    {
        $request = new WebhookRequest(
            static::createStub(RequestInterface::class),
            [],
            '',
            1700000000,
        );
        $result = new WebhookResult(
            body: null,
            statusCode: null,
            reasonPhrase: null,
            headers: null,
        );

        $response = DeliveryResponse::from($request, $result);

        static::assertNull($response->responseContent);
        static::assertNull($response->responseStatusCode);
        static::assertNull($response->responseReasonPhrase);
    }

    #[TestDox('toArray() drops null values')]
    public function testToArrayFiltersNulls(): void
    {
        $response = new DeliveryResponse(requestContent: '{}');

        static::assertSame(['request_content' => '{}'], $response->toArray());
    }
}

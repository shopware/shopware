<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Outbox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Service\WebhookRequest;
use Shopware\Core\Framework\Webhook\Service\WebhookResult;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeliveryResponse::class)]
class DeliveryResponseTest extends TestCase
{
    public function testFromBuildsResponseFromSuccessfulCall(): void
    {
        $request = new WebhookRequest(
            request: $this->createMock(RequestInterface::class),
            headers: ['X-Foo' => 'bar'],
            body: '{"event":"x"}',
            timestamp: 1234567890,
        );
        $result = new WebhookResult(
            body: '{"ok":true}',
            statusCode: 200,
            reasonPhrase: 'OK',
            headers: ['Content-Type' => ['application/json']],
            processingTimeSeconds: 5,
        );

        $response = DeliveryResponse::from($request, $result);

        static::assertSame('{"headers":{"X-Foo":"bar"},"body":"{\\"event\\":\\"x\\"}"}', $response->requestContent);
        static::assertSame('{"headers":{"Content-Type":["application\/json"]},"body":"{\\"ok\\":true}"}', $response->responseContent);
        static::assertSame(200, $response->responseStatusCode);
        static::assertSame('OK', $response->responseReasonPhrase);
        static::assertSame(5, $response->processingTimeSeconds);
    }

    public function testFromOmitsResponseContentWhenResultHasNoResponse(): void
    {
        $request = new WebhookRequest(
            request: $this->createMock(RequestInterface::class),
            headers: [],
            body: '',
            timestamp: 0,
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

    public function testToArrayFiltersNulls(): void
    {
        $response = new DeliveryResponse(
            requestContent: 'req',
            processingTimeSeconds: null,
            responseContent: null,
            responseStatusCode: null,
            responseReasonPhrase: null,
        );

        static::assertSame(['request_content' => 'req'], $response->toArray());
    }

    public function testToArrayKeepsAllPopulatedFields(): void
    {
        $response = new DeliveryResponse(
            requestContent: 'req',
            processingTimeSeconds: 7,
            responseContent: 'resp',
            responseStatusCode: 200,
            responseReasonPhrase: 'OK',
        );

        static::assertSame([
            'request_content' => 'req',
            'processing_time' => 7,
            'response_content' => 'resp',
            'response_status_code' => 200,
            'response_reason_phrase' => 'OK',
        ], $response->toArray());
    }
}

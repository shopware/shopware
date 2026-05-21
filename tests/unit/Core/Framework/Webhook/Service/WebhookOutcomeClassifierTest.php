<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookDeliveryOutcome;

/**
 * Pinned vocabulary test for the bounded `outcome` label on `webhook.delivery.duration`.
 * A typo here silently mislabels every delivery histogram bucket.
 *
 * @internal
 */
#[CoversClass(WebhookDeliveryOutcome::class)]
class WebhookOutcomeClassifierTest extends TestCase
{
    /**
     * @return iterable<string, array{0: ?int, 1: WebhookDeliveryOutcome}>
     */
    public static function statusCodeProvider(): iterable
    {
        yield 'null status code → network_error' => [null, WebhookDeliveryOutcome::NETWORK_ERROR];
        yield 'HTTP 408 → client_error (4xx)' => [408, WebhookDeliveryOutcome::CLIENT_ERROR];
        yield 'HTTP 429 → client_error (4xx)' => [429, WebhookDeliveryOutcome::CLIENT_ERROR];
        yield 'HTTP 404 → client_error (4xx)' => [404, WebhookDeliveryOutcome::CLIENT_ERROR];
        yield 'HTTP 499 → client_error (4xx)' => [499, WebhookDeliveryOutcome::CLIENT_ERROR];
        yield 'HTTP 500 → server_error (5xx)' => [500, WebhookDeliveryOutcome::SERVER_ERROR];
        yield 'HTTP 503 → server_error (5xx)' => [503, WebhookDeliveryOutcome::SERVER_ERROR];
        yield 'HTTP 599 → server_error (5xx)' => [599, WebhookDeliveryOutcome::SERVER_ERROR];
        yield 'HTTP 200 → success' => [200, WebhookDeliveryOutcome::SUCCESS];
        yield 'HTTP 201 → success' => [201, WebhookDeliveryOutcome::SUCCESS];
        yield 'HTTP 304 → success' => [304, WebhookDeliveryOutcome::SUCCESS];
        yield 'HTTP 399 → success' => [399, WebhookDeliveryOutcome::SUCCESS];
    }

    #[DataProvider('statusCodeProvider')]
    public function testClassifyOutcome(?int $statusCode, WebhookDeliveryOutcome $expectedOutcome): void
    {
        static::assertSame($expectedOutcome, WebhookDeliveryOutcome::fromStatusCode($statusCode));
    }
}

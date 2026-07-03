<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\HttpErrorClassifier;

/**
 * Locks down the status-code-to-classification table the circuit breaker feeds on (ADR §"Error
 * classification"). The split decides how hard the breaker reacts: transient classes only count
 * toward DEGRADED, the auth class builds the suspension streak, 410 suspends outright, and the
 * payload class never touches health — so a misclassified code either kills a good endpoint or
 * keeps hammering a dead one.
 *
 * @internal
 */
#[CoversClass(HttpErrorClassifier::class)]
class HttpErrorClassifierTest extends TestCase
{
    #[DataProvider('transportFailureProvider')]
    public function testTransportFailureWithoutResponseIsTransientNetwork(?\Throwable $exception): void
    {
        static::assertSame(ErrorClassification::TransientNetwork, (new HttpErrorClassifier())->classify(0, $exception));
    }

    /**
     * @return iterable<string, array{?\Throwable}>
     */
    public static function transportFailureProvider(): iterable
    {
        // Status 0 = no HTTP response; the exception carries the transport failure. All are
        // transient: a cert-renewal blip or DNS jitter must not suspend an endpoint.
        yield 'connection refused' => [new \RuntimeException('cURL error 7: connection refused')];
        yield 'DNS resolution failed' => [new \RuntimeException('cURL error 6: could not resolve host')];
        yield 'read timeout' => [new \RuntimeException('cURL error 28: operation timed out')];
        yield 'TLS handshake failed' => [new \RuntimeException('cURL error 35: SSL connect error')];
        yield 'no exception attached' => [null];
    }

    #[DataProvider('successStatusProvider')]
    public function testTwoHundredsClassifyAsSuccess(int $statusCode): void
    {
        static::assertSame(ErrorClassification::Success, (new HttpErrorClassifier())->classify($statusCode));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function successStatusProvider(): iterable
    {
        yield '200 OK' => [200];
        yield '201 Created' => [201];
        yield '204 No Content' => [204];
        yield '299 (upper 2xx bound)' => [299];
    }

    #[DataProvider('redirectStatusProvider')]
    public function testUnfollowedRedirectsAreTransientRedirect(int $statusCode): void
    {
        // A redirect is endpoint configuration, not message content — it escalates through
        // DEGRADED instead of failing invisibly forever.
        static::assertSame(ErrorClassification::TransientRedirect, (new HttpErrorClassifier())->classify($statusCode));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function redirectStatusProvider(): iterable
    {
        yield '300 Multiple Choices (lower 3xx bound)' => [300];
        yield '301 Moved Permanently' => [301];
        yield '302 Found' => [302];
        yield '308 Permanent Redirect' => [308];
        yield '399 (upper 3xx bound)' => [399];
    }

    public function testRateLimitingIsTransientRateLimit(): void
    {
        static::assertSame(ErrorClassification::TransientRateLimit, (new HttpErrorClassifier())->classify(429));
    }

    #[DataProvider('transientServerStatusProvider')]
    public function testServerFailuresIncludingNotFoundAreTransientServer(int $statusCode): void
    {
        static::assertSame(ErrorClassification::TransientServer, (new HttpErrorClassifier())->classify($statusCode));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function transientServerStatusProvider(): iterable
    {
        // 404 is transient: usually a deploy or config change in flight. A persistent 404 still
        // escalates through the DEGRADED budget.
        yield '404 Not Found' => [404];
        yield '408 Request Timeout' => [408];
        yield '500 Internal Server Error' => [500];
        yield '502 Bad Gateway' => [502];
        yield '503 Service Unavailable' => [503];
        yield '599 (upper 5xx bound)' => [599];
    }

    #[DataProvider('authStatusProvider')]
    public function testAuthRejectionsAreNonTransientAuth(int $statusCode): void
    {
        // Auth suspends only as a streak — one spurious WAF/CDN 401 is a blip, not a verdict.
        static::assertSame(ErrorClassification::NonTransientAuth, (new HttpErrorClassifier())->classify($statusCode));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function authStatusProvider(): iterable
    {
        yield '401 Unauthorized' => [401];
        yield '403 Forbidden' => [403];
    }

    public function testGoneIsTheEndpointRetirementSignal(): void
    {
        static::assertSame(ErrorClassification::NonTransientEndpoint, (new HttpErrorClassifier())->classify(410));
    }

    #[DataProvider('payloadStatusProvider')]
    public function testPayloadRejectionsClassifyAsNonTransientPayload(int $statusCode): void
    {
        // 400 and every unlisted 4xx blame the message, not the endpoint — one malformed event
        // must never disable a good endpoint.
        static::assertSame(ErrorClassification::NonTransientPayload, (new HttpErrorClassifier())->classify($statusCode));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function payloadStatusProvider(): iterable
    {
        yield '400 Bad Request' => [400];
        yield '405 Method Not Allowed' => [405];
        yield '415 Unsupported Media Type' => [415];
        yield '418 I am a teapot (unexpected)' => [418];
        yield '422 Unprocessable Entity' => [422];
        yield '451 Unavailable For Legal Reasons' => [451];
    }
}

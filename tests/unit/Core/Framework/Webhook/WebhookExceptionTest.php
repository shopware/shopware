<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookException::class)]
class WebhookExceptionTest extends TestCase
{
    public function testAppWebhookFailedException(): void
    {
        $e = WebhookException::appWebhookFailedException('webhookId', 'appId', new \Exception('error'));
        static::assertSame('Webhook "webhookId" from "appId" failed with error: error.', $e->getMessage());
        static::assertSame('FRAMEWORK__APP_WEBHOOK_FAILED', $e->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
    }

    public function testWebhookFailedException(): void
    {
        $e = WebhookException::webhookFailedException('webhookId', new \Exception('error'));
        static::assertSame('Webhook "webhookId" failed with error: error.', $e->getMessage());
        static::assertSame('FRAMEWORK__WEBHOOK_FAILED', $e->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
    }

    public function testInvalidDataMapping(): void
    {
        $exception = WebhookException::invalidDataMapping('propertyName', 'classString');

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame('Invalid available DataMapping, could not get property "propertyName" on instance of classString', $exception->getMessage());

            return;
        }

        static::assertInstanceOf(WebhookException::class, $exception);
        static::assertSame('Invalid available DataMapping, could not get property "propertyName" on instance of classString', $exception->getMessage());
        static::assertSame('FRAMEWORK__WEBHOOK_INVALID_DATA_MAPPING', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testUnknownEventDataType(): void
    {
        $exception = WebhookException::unknownEventDataType('invalidType');

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame('Unknown EventDataType: invalidType', $exception->getMessage());

            return;
        }

        static::assertInstanceOf(WebhookException::class, $exception);
        static::assertSame('Unknown EventDataType: invalidType', $exception->getMessage());
        static::assertSame('FRAMEWORK__WEBHOOK_UNKNOWN_DATA_TYPE', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testUnsupportedMessage(): void
    {
        $e = WebhookException::unsupportedMessage('stdClass');

        static::assertSame('The webhook transport only supports WebhookEventMessage, got "stdClass".', $e->getMessage());
        static::assertSame('FRAMEWORK__WEBHOOK_UNSUPPORTED_MESSAGE', $e->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
    }

    /**
     * @param \Closure(): WebhookException $factory
     */
    #[DataProvider('healthApiExceptionProvider')]
    public function testHealthApiException(\Closure $factory, int $statusCode, string $errorCode): void
    {
        $exception = $factory();

        static::assertSame($statusCode, $exception->getStatusCode());
        static::assertSame($errorCode, $exception->getErrorCode());
    }

    /**
     * @return iterable<string, array{\Closure(): WebhookException, int, string}>
     */
    public static function healthApiExceptionProvider(): iterable
    {
        yield 'health API disabled' => [
            static fn (): WebhookException => WebhookException::healthApiDisabled(),
            Response::HTTP_NOT_FOUND,
            'FRAMEWORK__WEBHOOK_HEALTH_API_DISABLED',
        ];

        yield 'missing integration' => [
            static fn (): WebhookException => WebhookException::missingIntegration(),
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__WEBHOOK_MISSING_INTEGRATION',
        ];

        yield 'app missing for integration' => [
            static fn (): WebhookException => WebhookException::appNotFoundForIntegration('integration-id'),
            Response::HTTP_NOT_FOUND,
            'FRAMEWORK__WEBHOOK_APP_NOT_FOUND_FOR_INTEGRATION',
        ];

        yield 'invalid reactivation names' => [
            static fn (): WebhookException => WebhookException::invalidReactivationNames(),
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__WEBHOOK_INVALID_REACTIVATION_NAMES',
        ];

        yield 'too many reactivation names' => [
            static fn (): WebhookException => WebhookException::tooManyReactivationNames(51, 50),
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__WEBHOOK_TOO_MANY_REACTIVATION_NAMES',
        ];

        yield 'reactivation throttled' => [
            static fn (): WebhookException => WebhookException::reactivationThrottled(60, new \RuntimeException()),
            Response::HTTP_TOO_MANY_REQUESTS,
            'FRAMEWORK__WEBHOOK_REACTIVATION_THROTTLED',
        ];

        yield 'operator route without user' => [
            static fn (): WebhookException => WebhookException::operatorRouteRequiresUser(),
            Response::HTTP_FORBIDDEN,
            'FRAMEWORK__WEBHOOK_OPERATOR_ROUTE_REQUIRES_USER',
        ];

        yield 'webhook not found' => [
            static fn (): WebhookException => WebhookException::webhookNotFound('webhook-id'),
            Response::HTTP_NOT_FOUND,
            'FRAMEWORK__WEBHOOK_NOT_FOUND',
        ];
    }
}

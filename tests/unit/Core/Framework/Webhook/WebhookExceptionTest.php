<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
        static::assertEquals('Webhook "webhookId" from "appId" failed with error: error.', $e->getMessage());
        static::assertEquals('FRAMEWORK__APP_WEBHOOK_FAILED', $e->getErrorCode());
        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
    }

    public function testWebhookFailedException(): void
    {
        $e = WebhookException::webhookFailedException('webhookId', new \Exception('error'));
        static::assertEquals('Webhook "webhookId" failed with error: error.', $e->getMessage());
        static::assertEquals('FRAMEWORK__WEBHOOK_FAILED', $e->getErrorCode());
        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
    }

    public function testTargetNotAllowed(): void
    {
        $exception = WebhookException::targetNotAllowed();

        static::assertSame('Webhook target is not allowed.', $exception->getMessage());
        static::assertSame('FRAMEWORK__WEBHOOK_TARGET_NOT_ALLOWED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testRedirectTargetNotAllowed(): void
    {
        $exception = WebhookException::redirectTargetNotAllowed();

        static::assertSame('Redirect target is not allowed.', $exception->getMessage());
        static::assertSame('FRAMEWORK__WEBHOOK_REDIRECT_TARGET_NOT_ALLOWED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testMaximumRedirectsExceeded(): void
    {
        $exception = WebhookException::maximumRedirectsExceeded();

        static::assertSame('Maximum redirects exceeded.', $exception->getMessage());
        static::assertSame('FRAMEWORK__WEBHOOK_MAXIMUM_REDIRECTS_EXCEEDED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testInvalidDataMapping(): void
    {
        $exception = WebhookException::invalidDataMapping('propertyName', 'classString');

        static::assertSame('Invalid available DataMapping, could not get property "propertyName" on instance of classString', $exception->getMessage());
    }

    public function testUnknownEventDataType(): void
    {
        $exception = WebhookException::unknownEventDataType('invalidType');

        static::assertSame('Unknown EventDataType: invalidType', $exception->getMessage());
    }
}

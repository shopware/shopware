<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
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
}

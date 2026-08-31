<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\EventLog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookEventLogEntity::class)]
class WebhookEventLogEntityTest extends TestCase
{
    public function testSerializedWebhookMessageRoundTrip(): void
    {
        $entity = new WebhookEventLogEntity();
        $entity->setSerializedWebhookMessage('serialized-message');

        static::assertSame('serialized-message', $entity->getSerializedWebhookMessage());
    }

    public function testAccessorsRoundTrip(): void
    {
        $entity = new WebhookEventLogEntity();

        $entity->setAppName('app');
        $entity->setWebhookName('hook');
        $entity->setEventName('checkout.order.placed');
        $entity->setDeliveryStatus('queued');
        $entity->setTimestamp(1725105600);
        $entity->setProcessingTime(42);
        $entity->setAppVersion('1.2.3');
        $entity->setRequestContent(['payload' => 'request']);
        $entity->setResponseContent(['payload' => 'response']);
        $entity->setResponseStatusCode(200);
        $entity->setResponseReasonPhrase('OK');
        $entity->setUrl('https://example.com/hook');
        $entity->setOnlyLiveVersion(true);
        $entity->setSequence(7);

        static::assertSame('app', $entity->getAppName());
        static::assertSame('hook', $entity->getWebhookName());
        static::assertSame('checkout.order.placed', $entity->getEventName());
        static::assertSame('queued', $entity->getDeliveryStatus());
        static::assertSame(1725105600, $entity->getTimestamp());
        static::assertSame(42, $entity->getProcessingTime());
        static::assertSame('1.2.3', $entity->getAppVersion());
        static::assertSame(['payload' => 'request'], $entity->getRequestContent());
        static::assertSame(['payload' => 'response'], $entity->getResponseContent());
        static::assertSame(200, $entity->getResponseStatusCode());
        static::assertSame('OK', $entity->getResponseReasonPhrase());
        static::assertSame('https://example.com/hook', $entity->getUrl());
        static::assertTrue($entity->getOnlyLiveVersion());
        static::assertSame(7, $entity->getSequence());
    }
}

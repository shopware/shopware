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
}

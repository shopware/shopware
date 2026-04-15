<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;

/**
 * @internal
 */
#[CoversClass(WebhookEventMessage::class)]
class WebhookEventMessageTest extends TestCase
{
    public function testPartitionKeyReturnsExplicitValue(): void
    {
        $msg = new WebhookEventMessage('e1', [], 'app-1', 'wh-1', '6.7', 'https://example.com', null, 'l', 'en', [], 'my-partition');

        static::assertSame('my-partition', $msg->getPartitionKey());
    }

    public function testPartitionKeyFallsBackToAppId(): void
    {
        $msg = new WebhookEventMessage('e1', [], 'app-1', 'wh-1', '6.7', 'https://example.com', null, 'l', 'en');

        static::assertSame('app-1', $msg->getPartitionKey());
    }

    public function testPartitionKeyFallsBackToDefaultForNonApp(): void
    {
        $msg = new WebhookEventMessage('e1', [], null, 'wh-1', '6.7', 'https://example.com', null, 'l', 'en');

        static::assertSame(WebhookEventMessage::DEFAULT_PARTITION_KEY, $msg->getPartitionKey());
    }
}

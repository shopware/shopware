<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Test\Assert\Serialization;

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

    public function testLegacySerializedMessageWithoutPartitionKeyFallsBackToAppId(): void
    {
        $msg = $this->removePartitionKeyFromSerializedMessage(
            new WebhookEventMessage('e1', [], 'app-1', 'wh-1', '6.7', 'https://example.com', null, 'l', 'en')
        );

        static::assertFalse($msg->isReworkEnvelope());
        static::assertSame('app-1', $msg->getPartitionKey());
    }

    private function removePartitionKeyFromSerializedMessage(WebhookEventMessage $message): WebhookEventMessage
    {
        $serialized = serialize($message);
        $serialized = preg_replace_callback(
            '/^O:(\d+):"([^"]+)":(\d+):\{/',
            static fn (array $matches): string => \sprintf(
                'O:%d:"%s":%d:{',
                (int) $matches[1],
                $matches[2],
                (int) $matches[3] - 1
            ),
            $serialized
        );
        static::assertIsString($serialized);
        $serialized = str_replace('s:12:"partitionKey";N;', '', $serialized);

        $legacy = Serialization::assertUnserializedInstanceOf(WebhookEventMessage::class, $serialized);
        static::assertInstanceOf(WebhookEventMessage::class, $legacy);

        return $legacy;
    }
}

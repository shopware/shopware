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
        $msg = new WebhookEventMessage('e1', [], 'app-1', 'wh-1', '6.7', 'https://x.com', null, 'l', 'en', [], 'my-partition');

        static::assertSame('my-partition', $msg->getPartitionKey());
    }

    public function testPartitionKeyFallsBackToAppId(): void
    {
        $msg = new WebhookEventMessage('e1', [], 'app-1', 'wh-1', '6.7', 'https://x.com', null, 'l', 'en');

        static::assertSame('app-1', $msg->getPartitionKey());
    }

    public function testPartitionKeyFallsBackToDefaultForNonApp(): void
    {
        $msg = new WebhookEventMessage('e1', [], null, 'wh-1', '6.7', 'https://x.com', null, 'l', 'en');

        static::assertSame('default', $msg->getPartitionKey());
    }

    public function testGettersReturnConstructorValues(): void
    {
        $payload = ['event' => 'product.written', 'data' => ['id' => '123']];
        $headers = ['X-Custom' => 'value'];

        $msg = new WebhookEventMessage(
            'evt-id-1',
            $payload,
            'app-id-1',
            'wh-id-1',
            '6.7.0',
            'https://example.com/hook',
            's3cret',
            'lang-1',
            'de-DE',
            $headers,
            'my-partition',
        );

        static::assertSame('evt-id-1', $msg->getWebhookEventId());
        static::assertSame($payload, $msg->getPayload());
        static::assertSame('app-id-1', $msg->getAppId());
        static::assertSame('wh-id-1', $msg->getWebhookId());
        static::assertSame('6.7.0', $msg->getShopwareVersion());
        static::assertSame('https://example.com/hook', $msg->getUrl());
        static::assertSame('s3cret', $msg->getSecret());
        static::assertSame('lang-1', $msg->getLanguageId());
        static::assertSame('de-DE', $msg->getUserLocale());
        static::assertSame($headers, $msg->getWebhookHeaders());
        static::assertSame('my-partition', $msg->getPartitionKey());
    }

    public function testNullableFieldsReturnNull(): void
    {
        $msg = new WebhookEventMessage('e1', [], null, 'wh-1', '6.7', 'https://x.com', null, 'l', 'en');

        static::assertNull($msg->getAppId());
        static::assertNull($msg->getSecret());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookEventMessage::class)]
class WebhookEventMessageTest extends TestCase
{
    public function testSerializationRoundTripPreservesAppName(): void
    {
        $restored = unserialize(serialize($this->message(appName: 'SwagApp')));

        static::assertInstanceOf(WebhookEventMessage::class, $restored);
        static::assertSame('SwagApp', $restored->getAppName());
        static::assertSame('s3cr3t', $restored->getSecret());
    }

    /**
     * Messages queued before $appName existed are still on the queue (and stored serialized in
     * webhook_event_log) during a rollout. Deserializing such a payload must not fail: the property
     * is absent, so getAppName() has to read as null rather than throw on an uninitialized typed
     * property — otherwise every in-flight delivery would error until the queue drains.
     */
    public function testLegacyMessageSerializedWithoutAppNameDeserializesToNull(): void
    {
        $legacyBlob = $this->stripAppName(serialize($this->message(appName: null)));

        $restored = unserialize($legacyBlob);

        static::assertInstanceOf(WebhookEventMessage::class, $restored);
        static::assertNull($restored->getAppName());
        static::assertSame('s3cr3t', $restored->getSecret());
    }

    private function message(?string $appName): WebhookEventMessage
    {
        return new WebhookEventMessage(
            'event-id',
            ['body' => 'payload'],
            Uuid::randomHex(),
            Uuid::randomHex(),
            '6.6.0.0',
            'https://example.com/webhook',
            's3cr3t',
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
            [],
            $appName,
        );
    }

    /**
     * Rewrites a serialized message into the pre-$appName shape: drops the (null) appName property
     * and decrements the object's property count, mirroring a payload written before the field existed.
     */
    private function stripAppName(string $serialized): string
    {
        $key = \sprintf("\0%s\0appName", WebhookEventMessage::class);
        $entry = \sprintf('s:%d:"%s";N;', \strlen($key), $key);

        $stripped = str_replace($entry, '', $serialized);
        static::assertNotSame($serialized, $stripped, 'Expected the serialized payload to carry the appName property');

        return str_replace(
            \sprintf('%s":11:{', WebhookEventMessage::class),
            \sprintf('%s":10:{', WebhookEventMessage::class),
            $stripped
        );
    }
}

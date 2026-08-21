<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\Framework\Webhook\WebhookEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookEntity::class)]
class WebhookEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $app = new AppEntity();

        $webhook = new WebhookEntity();
        $webhook->setName('Order placed');
        $webhook->setEventName('checkout.order.placed');
        $webhook->setUrl('https://example.com/hook');
        $webhook->setOnlyLiveVersion(true);
        $webhook->setAppId('app-id');
        $webhook->setApp($app);
        $webhook->setActive(false);
        $webhook->setErrorCount(3);

        static::assertSame('Order placed', $webhook->getName());
        static::assertSame('checkout.order.placed', $webhook->getEventName());
        static::assertSame('https://example.com/hook', $webhook->getUrl());
        static::assertTrue($webhook->getOnlyLiveVersion());
        static::assertSame('app-id', $webhook->getAppId());
        static::assertSame($app, $webhook->getApp());
        static::assertFalse($webhook->isActive());
        static::assertSame(3, $webhook->getErrorCount());
    }

    /**
     * @param \Closure(WebhookEntity): void $write
     * @param \Closure(WebhookEntity): mixed $read
     */
    #[TestDox('$_dataName is readable outside of a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsReadableOutsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $webhook = $this->webhookWithInternalProperties();
        $write($webhook);

        static::assertSame($expected, $read($webhook));
    }

    /**
     * @param \Closure(WebhookEntity): void $write
     * @param \Closure(WebhookEntity): mixed $read
     */
    #[TestDox('$_dataName is guarded inside a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsGuardedInsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $webhook = $this->webhookWithInternalProperties();
        $write($webhook);

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed($property, WebhookEntity::class));
        $read($webhook);
    }

    /**
     * @return \Generator<string, array{0: \Closure(WebhookEntity): void, 1: \Closure(WebhookEntity): mixed, 2: mixed, 3: string}>
     */
    public static function internalPropertyProvider(): \Generator
    {
        yield 'ownerUserId' => [
            static fn (WebhookEntity $webhook) => $webhook->setOwnerUserId('user-id'),
            static fn (WebhookEntity $webhook) => $webhook->getOwnerUserId(),
            'user-id',
            'ownerUserId',
        ];

        yield 'ownerIntegrationId' => [
            static fn (WebhookEntity $webhook) => $webhook->setOwnerIntegrationId('integration-id'),
            static fn (WebhookEntity $webhook) => $webhook->getOwnerIntegrationId(),
            'integration-id',
            'ownerIntegrationId',
        ];
    }

    private function webhookWithInternalProperties(): WebhookEntity
    {
        $webhook = new WebhookEntity();
        $webhook->internalSetEntityData(WebhookDefinition::ENTITY_NAME, new FieldVisibility(['ownerUserId', 'ownerIntegrationId']));

        return $webhook;
    }
}

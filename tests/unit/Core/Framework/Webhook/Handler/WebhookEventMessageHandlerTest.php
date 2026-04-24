<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Handler;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookRequest;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookEventMessageHandler::class)]
class WebhookEventMessageHandlerTest extends TestCase
{
    public function testFlagOffSuccessDoesNotResetErrorCountWhenSuccessWriteLosesAttemptOwnership(): void
    {
        $webhookId = Uuid::randomHex();
        $message = new WebhookEventMessage(
            webhookEventId: Uuid::randomHex(),
            payload: ['body' => 'payload'],
            appId: Uuid::randomHex(),
            webhookId: $webhookId,
            shopwareVersion: '6.7.0',
            url: 'https://example.com/webhook',
            secret: 'test-secret',
            languageId: Defaults::LANGUAGE_SYSTEM,
            userLocale: 'en-GB',
            partitionKey: 'test-partition',
        );

        $request = new WebhookRequest(
            request: new Request('POST', 'https://example.com/webhook', ['Content-Type' => 'application/json'], '{"body":"payload"}'),
            headers: ['Content-Type' => 'application/json'],
            body: '{"body":"payload"}',
            timestamp: 1713182400,
            options: [],
        );

        $outbox = $this->createMock(OutboxEventRepository::class);
        $outbox->method('hasDeliveryRow')->willReturn(true);
        $outbox->expects($this->once())->method('markRunning')
            ->with($message->getWebhookEventId())
            ->willReturn(new OutboxEntry($message->getWebhookEventId(), 10, 2, 'running'));
        $outbox->expects($this->once())->method('markSuccess')
            ->with(
                $message->getWebhookEventId(),
                static::isInstanceOf(DeliveryResponse::class),
                2,
                10,
            )
            ->willReturn(false);

        $deliveryService = $this->createMock(WebhookDeliveryService::class);
        $deliveryService->expects($this->once())->method('buildRequest')
            ->willReturn($request);
        $deliveryService->expects($this->never())->method('deliver');

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->never())->method('updateRelated');

        $guzzle = new MockHandler([new Response(200, [], '{"ok": true}')]);
        $webhookClient = new WebhookClient(
            new Client(['handler' => HandlerStack::create($guzzle)]),
            new MockClock(new \DateTimeImmutable('2026-04-15 12:00:00')),
        );

        $handler = new WebhookEventMessageHandler(
            $webhookClient,
            $relatedWebhooks,
            $outbox,
            $deliveryService,
            $this->createMock(LoggerInterface::class),
        );

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', static function () use ($handler, $message): void {
            $handler($message);
        });
    }

    public function testFlagOffLegacyTerminalRedeliveryIsAckedWithoutSendingAgain(): void
    {
        $message = new WebhookEventMessage(
            webhookEventId: Uuid::randomHex(),
            payload: ['body' => 'payload'],
            appId: Uuid::randomHex(),
            webhookId: Uuid::randomHex(),
            shopwareVersion: '6.7.0',
            url: 'https://example.com/webhook',
            secret: 'test-secret',
            languageId: Defaults::LANGUAGE_SYSTEM,
            userLocale: 'en-GB',
        );

        $outbox = $this->createMock(OutboxEventRepository::class);
        $outbox->method('hasDeliveryRow')->willReturn(false);
        $outbox->expects($this->once())->method('ensureOutboxEntry')->willReturn(null);
        $outbox->expects($this->once())->method('backfillDelivery')->willReturn(null);
        $outbox->expects($this->once())->method('markRunning')->willReturn(null);
        $outbox->expects($this->once())->method('loadEventLogStatus')
            ->with($message->getWebhookEventId())
            ->willReturn(WebhookEventLogDefinition::STATUS_SUCCESS);

        $deliveryService = $this->createMock(WebhookDeliveryService::class);
        $deliveryService->expects($this->never())->method('buildRequest');
        $deliveryService->expects($this->never())->method('deliver');

        $relatedWebhooks = $this->createMock(RelatedWebhooks::class);
        $relatedWebhooks->expects($this->never())->method('updateRelated');

        $guzzle = new MockHandler([new Response(200, [], '{"ok": true}')]);
        $webhookClient = new WebhookClient(
            new Client(['handler' => HandlerStack::create($guzzle)]),
            new MockClock(new \DateTimeImmutable('2026-04-15 12:00:00')),
        );

        $handler = new WebhookEventMessageHandler(
            $webhookClient,
            $relatedWebhooks,
            $outbox,
            $deliveryService,
            $this->createMock(LoggerInterface::class),
        );

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', static function () use ($handler, $message): void {
            $handler($message);
        });
    }
}

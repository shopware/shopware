<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\Event\AppLifecycleEvent;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Profiling\Profiler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class WebhookManager implements ResetInterface
{
    /**
     * @var array<string, list<Webhook>>
     */
    private ?array $webhooks = null;

    public function __construct(
        private readonly WebhookLoader $webhookLoader,
        private readonly HookableEventFactory $eventFactory,
        private readonly AppLocaleProvider $appLocaleProvider,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
        private readonly WebhookClient $webhookClient,
        private readonly MessageBusInterface $bus,
        private readonly string $shopUrl,
        private readonly string $shopwareVersion,
        private readonly bool $isAdminWorkerEnabled,
        private readonly WebhookDeliveryService $webhookDeliveryService,
        private readonly WebhookOutboxStore $webhookOutboxStore,
        private readonly PolicyRegistry $policies,
    ) {
    }

    public function dispatch(object $event): void
    {
        $context = Context::createDefaultContext();

        foreach ($this->eventFactory->createHookablesFor($event) as $hookable) {
            $useEventContext = $event instanceof FlowEventAware || $event instanceof AppChangedEvent || $event instanceof EntityWrittenContainerEvent;

            $this->callWebhooks($hookable, $useEventContext ? $event->getContext() : $context);
        }
    }

    public function reset(): void
    {
        $this->webhooks = null;
    }

    public function clearInternalWebhookCache(): void
    {
        $this->webhooks = null;
    }

    private function callWebhooks(Hookable $event, Context $context): void
    {
        $webhooksForEvent = $this->filterWebhooksByLiveVersion($this->getWebhooks($event->getName()), $event);
        $webhooksForEvent = $this->filterWebhooksByPolicies($webhooksForEvent, $event);

        if ($webhooksForEvent === []) {
            return;
        }

        $languageId = $context->getLanguageId();
        $userLocale = $this->appLocaleProvider->getLocaleFromContext($context);

        if (Feature::isActive('WEBHOOKS_REWORK')) {
            $messages = $this->collectMessages($webhooksForEvent, $event, $languageId, $userLocale);

            if ($messages !== []) {
                Feature::silent('v6.8.0.0', fn () => $this->webhookDeliveryService->process($messages, forceSynchronous: $event instanceof AppLifecycleEvent));
            }

            return;
        }

        // Legacy paths — no feature flag
        if ($this->isAdminWorkerEnabled || $event instanceof AppLifecycleEvent) {
            Profiler::trace(
                'webhook::dispatch-sync',
                fn () => $this->callWebhooksSynchronous($webhooksForEvent, $event, $languageId, $userLocale)
            );

            return;
        }

        Profiler::trace(
            'webhook::dispatch-async',
            fn () => $this->dispatchWebhooksToQueue($webhooksForEvent, $event, $languageId, $userLocale)
        );
    }

    /**
     * @param array<Webhook> $webhooksForEvent
     *
     * @return list<WebhookEventMessage>
     */
    private function collectMessages(array $webhooksForEvent, Hookable $event, string $languageId, string $userLocale): array
    {
        $messages = [];

        foreach ($webhooksForEvent as $webhook) {
            $message = $this->createWebhookMessage($webhook, $event, $languageId, $userLocale);
            if ($message === null) {
                continue;
            }

            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * @deprecated tag:v6.8.0 — pre-WEBHOOKS_REWORK path; will be removed.
     *
     * @param array<Webhook> $webhooksForEvent
     */
    private function dispatchWebhooksToQueue(
        array $webhooksForEvent,
        Hookable $event,
        string $languageId,
        string $userLocale
    ): void {
        foreach ($webhooksForEvent as $webhook) {
            $message = $this->createWebhookMessage($webhook, $event, $languageId, $userLocale);
            if ($message === null) {
                continue;
            }

            $this->bus->dispatch($message);
        }
    }

    /**
     * @deprecated tag:v6.8.0 — pre-WEBHOOKS_REWORK path; will be removed.
     *
     * @param array<Webhook> $webhooksForEvent
     */
    private function callWebhooksSynchronous(
        array $webhooksForEvent,
        Hookable $event,
        string $languageId,
        string $userLocale
    ): void {
        $requests = [];
        /** @var array<string, OutboxEntry> $entries */
        $entries = [];

        foreach ($webhooksForEvent as $webhook) {
            $message = $this->createWebhookMessage($webhook, $event, $languageId, $userLocale);
            if ($message === null) {
                continue;
            }

            $this->webhookOutboxStore->recordOutboxEntry(OutboxInsert::fromMessage($message));
            $entry = $this->webhookOutboxStore->markRunning($message->getWebhookEventId());
            if ($entry === null) {
                continue;
            }

            $requests[$message->getWebhookEventId()] = $this->webhookDeliveryService->buildRequest($message, $entry);
            $entries[$message->getWebhookEventId()] = $entry;
        }

        $results = $this->webhookClient->sendBatch($requests);

        foreach ($results as $eventId => $result) {
            try {
                $request = $requests[$eventId];
                $entry = $entries[$eventId];

                $response = DeliveryResponse::from($request, $result);

                if ($result->successful()) {
                    $this->webhookOutboxStore->markSuccess($entry, $response);
                } else {
                    $this->webhookOutboxStore->markFailed($entry, $response);
                }
            } catch (\Throwable) {
                // Don't let one entry block the rest — failed entries stay in 'running'
            }
        }
    }

    private function createWebhookMessage(
        Webhook $webhook,
        Hookable $event,
        string $languageId,
        string $userLocale
    ): ?WebhookEventMessage {
        try {
            $webhookData = $this->getPayloadForWebhook($webhook, $event);
        } catch (ShopIdChangeSuggestedException) {
            // don't dispatch webhooks for apps if url changed
            return null;
        }

        $webhookHeaders = $event instanceof AppFlowActionEvent
            ? $event->getWebhookHeaders()
            : [];

        // partition by app for now. Later, PartitionAwareHookable allows event-level partitioning.
        $partitionKey = $webhook->appId ?? WebhookEventMessage::DEFAULT_PARTITION_KEY;

        return new WebhookEventMessage(
            $webhookData['source']['eventId'],
            $webhookData,
            $webhook->appId,
            $webhook->id,
            $this->shopwareVersion,
            $webhook->url,
            $webhook->appSecret,
            $languageId,
            $userLocale,
            $webhookHeaders,
            $partitionKey,
            $webhook->appName,
        );
    }

    /**
     * @return array{
     *     data: array{payload: array<string, mixed>, event: string},
     *     source: array{url: string, eventId: string, action?: string}
     * }|array<string, mixed>
     */
    private function getPayloadForWebhook(Webhook $webhook, Hookable $event): array
    {
        $source = [
            'url' => $this->shopUrl,
            'eventId' => Uuid::randomHex(),
        ];

        if ($webhook->appId !== null && $webhook->appVersion !== null) {
            $source = \array_merge(
                $source,
                $this->appPayloadServiceHelper->buildSource($webhook->appVersion, $webhook->appName ?? '')->jsonSerialize()
            );
        }

        if ($event instanceof AppFlowActionEvent) {
            $source['action'] = $event->getName();
            $payload = $event->getWebhookPayload();
            $payload['source'] = $source;

            return $payload;
        }

        $data = [
            'payload' => $this->filterPayloadByLiveVersion($event->getWebhookPayload(), $webhook, $event),
            'event' => $event->getName(),
        ];

        return [
            'data' => $data,
            'source' => $source,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function filterPayloadByLiveVersion(array $payload, Webhook $webhook, Hookable $event): array
    {
        if (!$event instanceof HookableEntityWrittenEvent || $webhook->onlyLiveVersion === false) {
            return $payload;
        }

        return array_filter($payload, static function ($writeResult) {
            return isset($writeResult['versionId']) && $writeResult['versionId'] === Defaults::LIVE_VERSION;
        });
    }

    /**
     * @return list<Webhook>
     */
    private function getWebhooks(string $eventName): array
    {
        $this->loadWebhooks();

        return $this->webhooks[$eventName] ?? [];
    }

    private function loadWebhooks(): void
    {
        if ($this->webhooks !== null) {
            return;
        }

        $webhooks = $this->webhookLoader->getWebhooks();
        foreach ($webhooks as $webhook) {
            $this->webhooks[$webhook->eventName][] = $webhook;
        }
    }

    /**
     * @param list<Webhook> $webhooks
     *
     * @return list<Webhook>
     */
    private function filterWebhooksByPolicies(array $webhooks, Hookable $event): array
    {
        return array_values(array_filter(
            $webhooks,
            fn (Webhook $webhook): bool => $this->policies->permitsDelivery($event, $webhook)
        ));
    }

    /**
     * @param list<Webhook> $webhooks
     *
     * @return list<Webhook>
     */
    private function filterWebhooksByLiveVersion(array $webhooks, Hookable $event): array
    {
        if (!$event instanceof HookableEntityWrittenEvent) {
            return $webhooks;
        }

        return array_values(array_filter($webhooks, static function (Webhook $webhook) use ($event): bool {
            if (!$webhook->onlyLiveVersion) {
                return true;
            }

            $isVersioned = false;

            foreach ($event->getWebhookPayload() as $writeResult) {
                if (isset($writeResult['versionId']) && $writeResult['versionId'] === Defaults::LIVE_VERSION) {
                    return true;
                }

                if (isset($writeResult['versionId'])) {
                    $isVersioned = true;
                }
            }

            // If the event is not versioned we should send the webhook,
            // only if it is versioned all results are not in the live version we skip it
            return !$isVersioned;
        }));
    }
}

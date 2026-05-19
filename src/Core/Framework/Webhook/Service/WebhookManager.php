<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Event\PreWebhooksDispatchEvent;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
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

    /**
     * @var array<string, mixed>
     */
    private array $privileges = [];

    public function __construct(
        private readonly WebhookLoader $webhookLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly HookableEventFactory $eventFactory,
        private readonly AppLocaleProvider $appLocaleProvider,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
        private readonly WebhookClient $webhookClient,
        private readonly MessageBusInterface $bus,
        private readonly string $shopUrl,
        private readonly string $shopwareVersion,
        private readonly bool $isAdminWorkerEnabled,
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
        $this->privileges = [];
    }

    public function clearInternalWebhookCache(): void
    {
        $this->webhooks = null;
    }

    public function clearInternalPrivilegesCache(): void
    {
        $this->privileges = [];
    }

    private function callWebhooks(Hookable $event, Context $context): void
    {
        $webhooksForEvent = $this->filterWebhooksByLiveVersion($this->getWebhooks($event->getName()), $event);

        if ($webhooksForEvent === []) {
            return;
        }

        $this->eventDispatcher->dispatch($e = new PreWebhooksDispatchEvent($webhooksForEvent));
        $webhooksForEvent = $e->webhooks;

        $languageId = $context->getLanguageId();
        $userLocale = $this->appLocaleProvider->getLocaleFromContext($context);

        $affectedRoleIds = array_values(array_filter(array_map(static fn (Webhook $webhook) => $webhook->appAclRoleId, $webhooksForEvent)));
        $this->loadPrivileges($event->getName(), $affectedRoleIds);

        // If the admin worker is enabled we send all events synchronously, as we can't guarantee timely delivery otherwise.
        // Additionally, all app lifecycle events are sent synchronously as those can lead to nasty race conditions otherwise.
        if ($this->isAdminWorkerEnabled || $event instanceof AppDeletedEvent || $event instanceof AppChangedEvent || $event instanceof AppPermissionsUpdated) {
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

            $this->logWebhookWithEvent($webhook, $message);
            $this->bus->dispatch($message);
        }
    }

    private function logWebhookWithEvent(Webhook $webhook, WebhookEventMessage $webhookEventMessage): void
    {
        $this->connection->insert(
            'webhook_event_log',
            [
                'id' => Uuid::fromHexToBytes($webhookEventMessage->getWebhookEventId()),
                'app_name' => $webhook->appName,
                'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
                'webhook_name' => $webhook->webhookName,
                'event_name' => $webhook->eventName,
                'app_version' => $webhook->appVersion,
                'url' => $webhook->url,
                'only_live_version' => (int) $webhook->onlyLiveVersion,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'serialized_webhook_message' => serialize($webhookEventMessage),
            ]
        );
    }

    /**
     * @param array<Webhook> $webhooksForEvent
     */
    private function callWebhooksSynchronous(
        array $webhooksForEvent,
        Hookable $event,
        string $languageId,
        string $userLocale
    ): void {
        $requests = [];
        foreach ($webhooksForEvent as $webhook) {
            $message = $this->createWebhookMessage($webhook, $event, $languageId, $userLocale);
            if ($message !== null) {
                $requests[$message->getWebhookEventId()] = $this->appPayloadServiceHelper->createWebhookRequest(
                    $message->getPayload(),
                    $message->getUrl(),
                    $message->getShopwareVersion(),
                    WebhookClient::CONNECT_TIMEOUT,
                    WebhookClient::REQUEST_TIMEOUT,
                    $message->getSecret(),
                    $message->getLanguageId(),
                    $message->getUserLocale(),
                    $message->getWebhookHeaders(),
                );
            }
        }

        $this->webhookClient->sendBatch($requests);
    }

    private function createWebhookMessage(
        Webhook $webhook,
        Hookable $event,
        string $languageId,
        string $userLocale
    ): ?WebhookEventMessage {
        if (!$this->isEventDispatchingAllowed($webhook, $event)) {
            return null;
        }

        try {
            $webhookData = $this->getPayloadForWebhook($webhook, $event);
        } catch (ShopIdChangeSuggestedException) {
            // don't dispatch webhooks for apps if url changed
            return null;
        }

        $webhookHeaders = $event instanceof AppFlowActionEvent
            ? $event->getWebhookHeaders()
            : [];

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
            $webhookHeaders
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

    private function isEventDispatchingAllowed(Webhook $webhook, Hookable $event): bool
    {
        if ($webhook->appId === null) {
            return true;
        }

        // Only app lifecycle hooks can be received if app is deactivated
        if ($webhook->appActive === false && !($event instanceof AppChangedEvent || $event instanceof AppDeletedEvent || $event instanceof AppPermissionsUpdated)) {
            return false;
        }

        $privileges = $this->privileges[$event->getName()][$webhook->appAclRoleId] ?? new AclPrivilegeCollection([]);

        return $event->isAllowed($webhook->appId, $privileges);
    }

    /**
     * @param list<string> $affectedRoleIds
     */
    private function loadPrivileges(string $eventName, array $affectedRoleIds): void
    {
        if (\array_key_exists($eventName, $this->privileges)) {
            return;
        }

        $this->privileges[$eventName] = $this->webhookLoader->getPrivilegesForRoles($affectedRoleIds);
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

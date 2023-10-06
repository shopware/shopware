<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Profiling\Profiler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @deprecated tag:v6.6.0 - Will be internal - reason:visibility-change
 */
#[Package('core')]
class WebhookDispatcher implements EventDispatcherInterface
{
    private ?WebhookCollection $webhooks = null;

    /**
     * @var array<string, mixed>
     */
    private array $privileges = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Connection $connection,
        private readonly Client $guzzle,
        private readonly string $shopUrl,
        private readonly ContainerInterface $container,
        private readonly HookableEventFactory $eventFactory,
        private readonly string $shopwareVersion,
        private readonly MessageBusInterface $bus,
        private readonly bool $isAdminWorkerEnabled
    ) {
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);

        if (EnvironmentHelper::getVariable('DISABLE_EXTENSIONS', false)) {
            return $event;
        }

        foreach ($this->eventFactory->createHookablesFor($event) as $hookable) {
            $context = Context::createDefaultContext();
            if ($event instanceof FlowEventAware || $event instanceof AppChangedEvent || $event instanceof EntityWrittenContainerEvent) {
                $context = $event->getContext();
            }

            $this->callWebhooks($hookable, $context);
        }

        // always return the original event and never our wrapped events
        // this would lead to problems in the `BusinessEventDispatcher` from core
        return $event;
    }

    /**
     * @param callable $listener can not use native type declaration @see https://github.com/symfony/symfony/issues/42283
     */
    public function addListener(string $eventName, $listener, int $priority = 0): void // @phpstan-ignore-line
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    /**
     * @return array<array-key, array<array-key, callable(object): void>|callable(object): void>
     */
    public function getListeners(?string $eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        /** @var callable(object): void $listener - Specify generic callback interface callers can provide more specific implementations */
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
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
        /** @var WebhookCollection $webhooksForEvent */
        $webhooksForEvent = $this->getWebhooks()->filterForEvent($event->getName());

        if ($webhooksForEvent->count() === 0) {
            return;
        }

        $affectedRoleIds = $webhooksForEvent->getAclRoleIdsAsBinary();
        $languageId = $context->getLanguageId();
        $userLocale = $this->getAppLocaleProvider()->getLocaleFromContext($context);

        // If the admin worker is enabled we send all events synchronously, as we can't guarantee timely delivery otherwise.
        // Additionally, all app lifecycle events are sent synchronously as those can lead to nasty race conditions otherwise.
        if ($this->isAdminWorkerEnabled || $event instanceof AppDeletedEvent || $event instanceof AppChangedEvent) {
            Profiler::trace('webhook::dispatch-sync', function () use ($userLocale, $languageId, $affectedRoleIds, $event, $webhooksForEvent): void {
                $this->callWebhooksSynchronous($webhooksForEvent, $event, $affectedRoleIds, $languageId, $userLocale);
            });

            return;
        }

        Profiler::trace('webhook::dispatch-async', function () use ($userLocale, $languageId, $affectedRoleIds, $event, $webhooksForEvent): void {
            $this->dispatchWebhooksToQueue($webhooksForEvent, $event, $affectedRoleIds, $languageId, $userLocale);
        });
    }

    private function getWebhooks(): WebhookCollection
    {
        if ($this->webhooks) {
            return $this->webhooks;
        }

        $criteria = new Criteria();
        $criteria->setTitle('apps::webhooks');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('app');

        /** @var WebhookCollection $webhooks */
        $webhooks = $this->container->get('webhook.repository')->search($criteria, Context::createDefaultContext())->getEntities();

        return $this->webhooks = $webhooks;
    }

    /**
     * @param array<string> $affectedRoles
     */
    private function isEventDispatchingAllowed(WebhookEntity $webhook, Hookable $event, array $affectedRoles): bool
    {
        $app = $webhook->getApp();

        if ($app === null) {
            return true;
        }

        // Only app lifecycle hooks can be received if app is deactivated
        if (!$app->isActive() && !($event instanceof AppChangedEvent || $event instanceof AppDeletedEvent)) {
            return false;
        }

        if (!($this->privileges[$event->getName()] ?? null)) {
            $this->loadPrivileges($event->getName(), $affectedRoles);
        }

        $privileges = $this->privileges[$event->getName()][$app->getAclRoleId()]
            ?? new AclPrivilegeCollection([]);

        if (!$event->isAllowed($app->getId(), $privileges)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string> $affectedRoleIds
     */
    private function callWebhooksSynchronous(
        WebhookCollection $webhooksForEvent,
        Hookable $event,
        array $affectedRoleIds,
        string $languageId,
        string $userLocale
    ): void {
        $requests = [];

        foreach ($webhooksForEvent as $webhook) {
            if (!$this->isEventDispatchingAllowed($webhook, $event, $affectedRoleIds)) {
                continue;
            }

            try {
                $webhookData = $this->getPayloadForWebhook($webhook, $event);
            } catch (AppUrlChangeDetectedException) {
                // don't dispatch webhooks for apps if url changed
                continue;
            }

            $timestamp = time();
            $webhookData['timestamp'] = $timestamp;

            /** @var string $jsonPayload */
            $jsonPayload = json_encode($webhookData, \JSON_THROW_ON_ERROR);

            $headers = [
                'Content-Type' => 'application/json',
                'sw-version' => $this->shopwareVersion,
                AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => $languageId,
                AuthMiddleware::SHOPWARE_USER_LANGUAGE => $userLocale,
            ];

            if ($event instanceof AppFlowActionEvent) {
                $headers = array_merge($headers, $event->getWebhookHeaders());
            }

            $request = new Request(
                'POST',
                $webhook->getUrl(),
                $headers,
                $jsonPayload
            );

            if ($webhook->getApp() !== null && $webhook->getApp()->getAppSecret() !== null) {
                $request = $request->withHeader(
                    RequestSigner::SHOPWARE_SHOP_SIGNATURE,
                    (new RequestSigner())->signPayload($jsonPayload, $webhook->getApp()->getAppSecret())
                );
            }

            $requests[] = $request;
        }

        if (\count($requests) > 0) {
            $pool = new Pool($this->guzzle, $requests);
            $pool->promise()->wait();
        }
    }

    /**
     * @param array<string> $affectedRoleIds
     */
    private function dispatchWebhooksToQueue(
        WebhookCollection $webhooksForEvent,
        Hookable $event,
        array $affectedRoleIds,
        string $languageId,
        string $userLocale
    ): void {
        foreach ($webhooksForEvent as $webhook) {
            if (!$this->isEventDispatchingAllowed($webhook, $event, $affectedRoleIds)) {
                continue;
            }

            try {
                $webhookData = $this->getPayloadForWebhook($webhook, $event);
            } catch (AppUrlChangeDetectedException) {
                // don't dispatch webhooks for apps if url changed
                continue;
            }

            $webhookEventId = $webhookData['source']['eventId'];

            $appId = $webhook->getApp() !== null ? $webhook->getApp()->getId() : null;
            $secret = $webhook->getApp() !== null ? $webhook->getApp()->getAppSecret() : null;

            $webhookEventMessage = new WebhookEventMessage(
                $webhookEventId,
                $webhookData,
                $appId,
                $webhook->getId(),
                $this->shopwareVersion,
                $webhook->getUrl(),
                $secret,
                $languageId,
                $userLocale
            );

            $this->logWebhookWithEvent($webhook, $webhookEventMessage);

            $this->bus->dispatch($webhookEventMessage);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayloadForWebhook(WebhookEntity $webhook, Hookable $event): array
    {
        $source = [
            'url' => $this->shopUrl,
            'eventId' => Uuid::randomHex(),
        ];

        if ($webhook->getApp() !== null) {
            $shopIdProvider = $this->getShopIdProvider();

            $source['appVersion'] = $webhook->getApp()->getVersion();
            $source['shopId'] = $shopIdProvider->getShopId();
        }

        if ($event instanceof AppFlowActionEvent) {
            $source['action'] = $event->getName();
            $payload = $event->getWebhookPayload();
            $payload['source'] = $source;

            return $payload;
        }

        $data = [
            'payload' => $event->getWebhookPayload($webhook->getApp()),
            'event' => $event->getName(),
        ];

        return [
            'data' => $data,
            'source' => $source,
        ];
    }

    private function logWebhookWithEvent(WebhookEntity $webhook, WebhookEventMessage $webhookEventMessage): void
    {
        /** @var EntityRepository $webhookEventLogRepository */
        $webhookEventLogRepository = $this->container->get('webhook_event_log.repository');

        $webhookEventLogRepository->create([
            [
                'id' => $webhookEventMessage->getWebhookEventId(),
                'appName' => $webhook->getApp()?->getName(),
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
                'webhookName' => $webhook->getName(),
                'eventName' => $webhook->getEventName(),
                'appVersion' => $webhook->getApp()?->getVersion(),
                'url' => $webhook->getUrl(),
                'serializedWebhookMessage' => serialize($webhookEventMessage),
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @param array<string> $affectedRoleIds
     */
    private function loadPrivileges(string $eventName, array $affectedRoleIds): void
    {
        $roles = $this->connection->fetchAllAssociative('
            SELECT `id`, `privileges`
            FROM `acl_role`
            WHERE `id` IN (:aclRoleIds)
        ', ['aclRoleIds' => $affectedRoleIds], ['aclRoleIds' => ArrayParameterType::STRING]);

        if (!$roles) {
            $this->privileges[$eventName] = [];
        }

        foreach ($roles as $privilege) {
            $this->privileges[$eventName][Uuid::fromBytesToHex($privilege['id'])]
                = new AclPrivilegeCollection(json_decode((string) $privilege['privileges'], true, 512, \JSON_THROW_ON_ERROR));
        }
    }

    private function getShopIdProvider(): ShopIdProvider
    {
        return $this->container->get(ShopIdProvider::class);
    }

    private function getAppLocaleProvider(): AppLocaleProvider
    {
        return $this->container->get(AppLocaleProvider::class);
    }
}

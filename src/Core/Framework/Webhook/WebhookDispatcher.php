<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class WebhookDispatcher implements EventDispatcherInterface
{
    private EventDispatcherInterface $dispatcher;

    private Connection $connection;

    private ?WebhookCollection $webhooks = null;

    private Client $guzzle;

    private string $shopUrl;

    private ContainerInterface $container;

    private array $privileges = [];

    private HookableEventFactory $eventFactory;

    private string $shopwareVersion;

    private MessageBusInterface $bus;

    private bool $isAdminWorkerEnabled;

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(
        EventDispatcherInterface $dispatcher,
        Connection $connection,
        Client $guzzle,
        string $shopUrl,
        ContainerInterface $container,
        HookableEventFactory $eventFactory,
        string $shopwareVersion,
        MessageBusInterface $bus,
        bool $isAdminWorkerEnabled
    ) {
        $this->dispatcher = $dispatcher;
        $this->connection = $connection;
        $this->guzzle = $guzzle;
        $this->shopUrl = $shopUrl;
        // inject container, so we can later get the ShopIdProvider and the webhook repository
        // ShopIdProvider, AppLocaleProvider and webhook repository can not be injected directly as it would lead to a circular reference
        $this->container = $container;
        $this->eventFactory = $eventFactory;
        $this->shopwareVersion = $shopwareVersion;
        $this->bus = $bus;
        $this->isAdminWorkerEnabled = $isAdminWorkerEnabled;
    }

    /**
     * @template TEvent of object
     *
     * @param TEvent $event
     *
     * @return TEvent
     */
    public function dispatch($event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);

        foreach ($this->eventFactory->createHookablesFor($event) as $hookable) {
            $context = Context::createDefaultContext();
            if (Feature::isActive('FEATURE_NEXT_17858')) {
                if ($event instanceof FlowEventAware || $event instanceof AppChangedEvent || $event instanceof EntityWrittenContainerEvent) {
                    $context = $event->getContext();
                }
            } else {
                if ($event instanceof BusinessEventInterface || $event instanceof AppChangedEvent || $event instanceof EntityWrittenContainerEvent) {
                    $context = $event->getContext();
                }
            }

            $this->callWebhooks($hookable, $context);
        }

        // always return the original event and never our wrapped events
        // this would lead to problems in the `BusinessEventDispatcher` from core
        return $event;
    }

    /**
     * @param string   $eventName
     * @param callable $listener
     * @param int      $priority
     */
    public function addListener($eventName, $listener, $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    /**
     * @param string   $eventName
     * @param callable $listener
     */
    public function removeListener($eventName, $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    /**
     * @param string|null $eventName
     */
    public function getListeners($eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    /**
     * @param string   $eventName
     * @param callable $listener
     */
    public function getListenerPriority($eventName, $listener): ?int
    {
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    /**
     * @param string|null $eventName
     */
    public function hasListeners($eventName = null): bool
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

        if ($this->isAdminWorkerEnabled) {
            $this->callWebhooksSynchronous($webhooksForEvent, $event, $affectedRoleIds, $languageId, $userLocale);

            return;
        }

        $this->dispatchWebhooksToQueue($webhooksForEvent, $event, $affectedRoleIds, $languageId, $userLocale);
    }

    private function getWebhooks(): WebhookCollection
    {
        if ($this->webhooks) {
            return $this->webhooks;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('app');

        if (!$this->container->has('webhook.repository')) {
            throw new ServiceNotFoundException('webhook.repository');
        }
        /** @var WebhookCollection $webhooks */
        $webhooks = $this->container->get('webhook.repository')->search($criteria, Context::createDefaultContext())->getEntities();

        return $this->webhooks = $webhooks;
    }

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
     * @param string[] $affectedRoleIds
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
            } catch (AppUrlChangeDetectedException $e) {
                // don't dispatch webhooks for apps if url changed
                continue;
            }

            $timestamp = time();
            $webhookData['timestamp'] = $timestamp;

            /** @var string $jsonPayload */
            $jsonPayload = json_encode($webhookData);

            $request = new Request(
                'POST',
                $webhook->getUrl(),
                [
                    'Content-Type' => 'application/json',
                    'sw-version' => $this->shopwareVersion,
                    AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => $languageId,
                    AuthMiddleware::SHOPWARE_USER_LANGUAGE => $userLocale,
                ],
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
     * @param string[] $affectedRoleIds
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
            } catch (AppUrlChangeDetectedException $e) {
                // don't dispatch webhooks for apps if url changed
                continue;
            }

            $webhookEventId = Uuid::randomHex();

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

    private function getPayloadForWebhook(WebhookEntity $webhook, Hookable $event): array
    {
        $data = [
            'payload' => $event->getWebhookPayload(),
            'event' => $event->getName(),
        ];

        $source = [
            'url' => $this->shopUrl,
        ];

        if ($webhook->getApp() !== null) {
            $shopIdProvider = $this->getShopIdProvider();

            $source['appVersion'] = $webhook->getApp()->getVersion();
            $source['shopId'] = $shopIdProvider->getShopId();
        }

        return [
            'data' => $data,
            'source' => $source,
        ];
    }

    private function logWebhookWithEvent(WebhookEntity $webhook, WebhookEventMessage $webhookEventMessage): void
    {
        if (!$this->container->has('webhook_event_log.repository')) {
            throw new ServiceNotFoundException('webhook_event_log.repository');
        }

        /** @var EntityRepositoryInterface $webhookEventLogRepository */
        $webhookEventLogRepository = $this->container->get('webhook_event_log.repository');

        $webhookEventLogRepository->create([
            [
                'id' => $webhookEventMessage->getWebhookEventId(),
                'appName' => $webhook->getApp() !== null ? $webhook->getApp()->getName() : null,
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
                'webhookName' => $webhook->getName(),
                'eventName' => $webhook->getEventName(),
                'appVersion' => $webhook->getApp() !== null ? $webhook->getApp()->getVersion() : null,
                'url' => $webhook->getUrl(),
                'serializedWebhookMessage' => serialize($webhookEventMessage),
            ],
        ], Context::createDefaultContext());
    }

    private function loadPrivileges(string $eventName, array $affectedRoleIds): void
    {
        $roles = $this->connection->fetchAll('
            SELECT `id`, `privileges`
            FROM `acl_role`
            WHERE `id` IN (:aclRoleIds)
        ', ['aclRoleIds' => $affectedRoleIds], ['aclRoleIds' => Connection::PARAM_STR_ARRAY]);

        if (!$roles) {
            $this->privileges[$eventName] = [];
        }

        foreach ($roles as $privilege) {
            $this->privileges[$eventName][Uuid::fromBytesToHex($privilege['id'])]
                = new AclPrivilegeCollection(json_decode($privilege['privileges'], true));
        }
    }

    private function getShopIdProvider(): ShopIdProvider
    {
        if (!$this->container->has(ShopIdProvider::class)) {
            throw new ServiceNotFoundException(ShopIdProvider::class);
        }

        return $this->container->get(ShopIdProvider::class);
    }

    private function getAppLocaleProvider(): AppLocaleProvider
    {
        if (!$this->container->has(AppLocaleProvider::class)) {
            throw new ServiceNotFoundException(AppLocaleProvider::class);
        }

        return $this->container->get(AppLocaleProvider::class);
    }
}

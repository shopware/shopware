<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppChangedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
        // ShopIdProvider and webhook repository can not be injected directly as it would lead to a circular reference
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
            $this->callWebhooks($hookable->getName(), $hookable);
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

    private function callWebhooks(string $eventName, Hookable $event): void
    {
        /** @var WebhookCollection $webhooksForEvent */
        $webhooksForEvent = $this->getWebhooks()->filterForEvent($eventName);

        if ($webhooksForEvent->count() === 0) {
            return;
        }

        $payload = $event->getWebhookPayload();
        $affectedRoleIds = $webhooksForEvent->getAclRoleIdsAsBinary();
        $requests = [];

        foreach ($webhooksForEvent as $webhook) {
            if ($webhook->getApp() !== null && !$this->isEventDispatchingAllowed($webhook->getApp(), $event, $affectedRoleIds)) {
                continue;
            }

            $payload = ['data' => ['payload' => $payload]];
            $payload['source']['url'] = $this->shopUrl;
            $payload['data']['event'] = $eventName;

            if ($webhook->getApp() !== null) {
                $payload['source']['appVersion'] = $webhook->getApp()->getVersion();
                $shopIdProvider = $this->getShopIdProvider();

                try {
                    $shopId = $shopIdProvider->getShopId();
                } catch (AppUrlChangeDetectedException $e) {
                    continue;
                }
                $payload['source']['shopId'] = $shopId;
            }

            if ($this->isAdminWorkerEnabled) {
                /** @var string $jsonPayload */
                $jsonPayload = json_encode($payload);

                $request = new Request(
                    'POST',
                    $webhook->getUrl(),
                    [
                        'Content-Type' => 'application/json',
                        'sw-version' => $this->shopwareVersion,
                    ],
                    $jsonPayload
                );

                if ($webhook->getApp() !== null && $webhook->getApp()->getAppSecret() !== null) {
                    $request = $request->withHeader(
                        'shopware-shop-signature',
                        hash_hmac('sha256', $jsonPayload, $webhook->getApp()->getAppSecret())
                    );
                }

                $requests[] = $request;
            } else {
                $webhookEventId = Uuid::randomHex();

                $appId = $webhook->getApp() !== null ? $webhook->getApp()->getId() : null;
                $webhookEventMessage = new WebhookEventMessage($webhookEventId, $payload, $appId, $webhook->getId(), $this->shopwareVersion, $webhook->getUrl());

                if (!$this->container->has('webhook_event_log.repository')) {
                    throw new ServiceNotFoundException('webhook_event_log.repository');
                }

                /** @var EntityRepositoryInterface $webhookEventLogRepository */
                $webhookEventLogRepository = $this->container->get('webhook_event_log.repository');

                $webhookEventLogRepository->create([
                    [
                        'id' => $webhookEventId,
                        'appName' => $webhook->getApp() !== null ? $webhook->getApp()->getName() : null,
                        'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
                        'webhookName' => $webhook->getName(),
                        'eventName' => $webhook->getEventName(),
                        'appVersion' => $webhook->getApp() !== null ? $webhook->getApp()->getVersion() : null,
                        'url' => $webhook->getUrl(),
                        'serializedWebhookMessage' => serialize($webhookEventMessage),
                    ],
                ], Context::createDefaultContext());

                $this->bus->dispatch($webhookEventMessage);
            }
        }

        if ($this->isAdminWorkerEnabled) {
            $pool = new Pool($this->guzzle, $requests);
            $pool->promise()->wait();
        }
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

    private function isEventDispatchingAllowed(AppEntity $app, Hookable $event, array $affectedRoles): bool
    {
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
}

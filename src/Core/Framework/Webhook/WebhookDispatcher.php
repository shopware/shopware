<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WebhookDispatcher implements EventDispatcherInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var WebhookCollection|null
     */
    private $webhooks;

    /**
     * @var Client
     */
    private $guzzle;

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $privileges = [];

    /**
     * @var HookableEventFactory
     */
    private $eventFactory;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        Connection $connection,
        Client $guzzle,
        string $shopUrl,
        ContainerInterface $container,
        HookableEventFactory $eventFactory
    ) {
        $this->dispatcher = $dispatcher;
        $this->connection = $connection;
        $this->guzzle = $guzzle;
        $this->shopUrl = $shopUrl;
        // inject container, so we can later get the ShopIdProvider
        // ShopIdProvider can not be injected directly as it would lead to a circular reference
        $this->container = $container;
        $this->eventFactory = $eventFactory;
    }

    /**
     * @param object $event
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
            if ($webhook->getApp()) {
                if (!$this->isEventDispatchingAllowed($webhook, $event, $affectedRoleIds)) {
                    continue;
                }
            }

            $payload = ['data' => ['payload' => $payload]];
            $payload['source']['url'] = $this->shopUrl;
            $payload['data']['event'] = $eventName;

            if ($webhook->getApp()) {
                $payload['source']['appVersion'] = $webhook->getApp()->getVersion();
                $shopIdProvider = $this->getShopIdProvider();

                try {
                    $shopId = $shopIdProvider->getShopId();
                } catch (AppUrlChangeDetectedException $e) {
                    continue;
                }
                $payload['source']['shopId'] = $shopId;
            }

            /** @var string $jsonPayload */
            $jsonPayload = \json_encode($payload);

            $request = new Request(
                'POST',
                $webhook->getUrl(),
                [
                    'Content-Type' => 'application/json',
                ],
                $jsonPayload
            );

            if ($webhook->getApp() && $webhook->getApp()->getAppSecret()) {
                $request = $request->withHeader(
                    'shopware-shop-signature',
                    hash_hmac('sha256', $jsonPayload, $webhook->getApp()->getAppSecret())
                );
            }

            $requests[] = $request;
        }

        $pool = new Pool($this->guzzle, $requests);
        $pool->promise()->wait();
    }

    private function getWebhooks(): WebhookCollection
    {
        if ($this->webhooks) {
            return $this->webhooks;
        }

        $criteria = new Criteria();
        $criteria->addAssociation('app')
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('app.active', true),
                new EqualsFilter('appId', null),
            ]));

        if (!$this->container->has('webhook.repository')) {
            throw new ServiceNotFoundException('webhook.repository');
        }
        /**
         * @var EntityRepositoryInterface $webhookRepository
         */
        $webhookRepository = $this->container->get('webhook.repository');

        /** @var WebhookCollection $webhooks */
        $webhooks = $webhookRepository->search($criteria, Context::createDefaultContext())->getEntities();

        return $this->webhooks = $webhooks;
    }

    private function isEventDispatchingAllowed(WebhookEntity $webhook, Hookable $event, array $affectedRoles): bool
    {
        if (!($this->privileges[$event->getName()] ?? null)) {
            $this->loadPrivileges($event->getName(), $affectedRoles);
        }

        $privileges = $this->privileges[$event->getName()][$webhook->getApp()->getAclRoleId()]
            ?? new AclPrivilegeCollection([]);

        if (!$event->isAllowed($webhook->getAppId(), $privileges)) {
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

        /** @var ShopIdProvider $shopIdProvider */
        $shopIdProvider = $this->container->get(ShopIdProvider::class);

        return $shopIdProvider;
    }
}

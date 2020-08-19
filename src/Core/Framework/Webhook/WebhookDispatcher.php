<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventWrapper\HookableBusinessEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
     * @var array|null
     */
    private $webhooks;

    /**
     * @var Client
     */
    private $guzzle;

    /**
     * @var BusinessEventEncoder
     */
    private $eventEncoder;

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        Connection $connection,
        Client $guzzle,
        BusinessEventEncoder $eventEncoder,
        string $shopUrl,
        ContainerInterface $container
    ) {
        $this->dispatcher = $dispatcher;
        $this->connection = $connection;
        $this->guzzle = $guzzle;
        $this->eventEncoder = $eventEncoder;
        $this->shopUrl = $shopUrl;
        // inject container, so we can later get the ShopIdProvider
        // ShopIdProvider can not be injected directly as it would lead to a circular reference
        $this->container = $container;
    }

    /**
     * @param object $event
     */
    public function dispatch($event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);

        if (!$event instanceof BusinessEventInterface && !$event instanceof Hookable) {
            return $event;
        }

        // BusinessEvent are the generic Events that get wrapped around the specific events
        // we don't want to dispatch those to the webhooks
        if ($event instanceof BusinessEvent) {
            return $event;
        }

        $hookableEvent = $event;
        if ($hookableEvent instanceof BusinessEventInterface) {
            $hookableEvent = HookableBusinessEvent::fromBusinessEvent($hookableEvent, $this->eventEncoder);
        }

        $this->callWebhooks($hookableEvent->getName(), $hookableEvent);

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

    public function clearInternalCache(): void
    {
        $this->webhooks = null;
    }

    private function callWebhooks(string $eventName, Hookable $event): void
    {
        if (!array_key_exists($eventName, $this->getWebhooks())) {
            return;
        }

        $payload = $event->getWebhookPayload();
        $requests = [];
        foreach ($this->getWebhooks()[$eventName] as $webhookConfig) {
            if ($webhookConfig['acl_role_id'] && $webhookConfig['app_id']) {
                if (!$this->isEventDispatchingAllowed($webhookConfig, $event)) {
                    continue;
                }
            }

            $payload = ['data' => ['payload' => $payload]];
            $payload['source']['url'] = $this->shopUrl;
            $payload['data']['event'] = $eventName;

            if ($webhookConfig['version']) {
                $payload['source']['appVersion'] = $webhookConfig['version'];
            }

            if ($webhookConfig['app_id']) {
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
                $webhookConfig['url'],
                [
                    'Content-Type' => 'application/json',
                ],
                $jsonPayload
            );

            if ($webhookConfig['app_secret']) {
                $request = $request->withHeader(
                    'shopware-shop-signature',
                    hash_hmac('sha256', $jsonPayload, $webhookConfig['app_secret'])
                );
            }

            $requests[] = $request;
        }

        $pool = new Pool($this->guzzle, $requests);
        $pool->promise()->wait();
    }

    private function getWebhooks(): array
    {
        if ($this->webhooks) {
            return $this->webhooks;
        }

        $result = $this->connection->fetchAll('
            SELECT `webhook`.`event_name`,
                   `webhook`.`url`,
                   `app`.`version`,
                   `app`.`app_secret`,
                   `app`.`id` AS `app_id`,
                   `app`.`acl_role_id`
            FROM `webhook` AS `webhook`
            LEFT JOIN `app` AS `app` ON `webhook`.`app_id` = `app`.`id`
            WHERE `webhook`.`app_id` IS NULL OR `app`.`active` = 1
        ');

        return $this->webhooks = FetchModeHelper::group($result);
    }

    private function isEventDispatchingAllowed(array $webhookConfig, Hookable $event): bool
    {
        $privileges = $this->connection->fetchColumn('
            SELECT `privileges`
            FROM `acl_role`
            WHERE `id` = :aclRoleId
        ', ['aclRoleId' => $webhookConfig['acl_role_id']]);
        $privileges = new AclPrivilegeCollection(json_decode($privileges ? $privileges : '[]', true));

        if (!$event->isAllowed(Uuid::fromBytesToHex($webhookConfig['app_id']), $privileges)) {
            return false;
        }

        return true;
    }

    private function getShopIdProvider(): ShopIdProvider
    {
        /**
         * @todo remove ignore-line once the next-10286 feature flag is removed
         * @phpstan-ignore-next-line
         *
         * @var ShopIdProvider $shopIdProvider
         */
        $shopIdProvider = $this->container->get(ShopIdProvider::class);

        return $shopIdProvider;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Tracking;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 *
 * @final
 */
#[Package('discovery')]
class SalesChannelTrackingListener implements EventSubscriberInterface
{
    final public const SESSION_KEY_REFERRAL_CODE = 'salesChannelReferralCode';

    final public const QUERY_PARAM = 'referringSalesChannel';

    private const TRACKABLE_TYPE_IDS = [
        Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE,
    ];

    private const CACHE_KEY_PREFIX = 'trackable-sales-channel-';

    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<SalesChannelTrackingOrderCollection> $salesChannelTrackingOrderRepository
     * @param EntityRepository<SalesChannelTrackingCustomerCollection> $salesChannelTrackingCustomerRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $salesChannelTrackingOrderRepository,
        private readonly EntityRepository $salesChannelTrackingCustomerRepository,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['storeReferralCode', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST],
            ],
            EntityWrittenContainerEvent::class => 'createTrackingRecords',
            SalesChannelEvents::SALES_CHANNEL_WRITTEN => 'invalidateTrackableChannelCache',
            SalesChannelEvents::SALES_CHANNEL_DELETED => 'invalidateTrackableChannelCache',
        ];
    }

    public function storeReferralCode(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        /** @var list<string> $scopes */
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\in_array('storefront', $scopes, true)) {
            return;
        }

        $referralCode = $request->query->get(self::QUERY_PARAM);

        if (!$referralCode || !Uuid::isValid($referralCode)) {
            return;
        }

        if (!$this->isTrackableChannel($referralCode)) {
            return;
        }

        if (!$request->hasSession(true)) {
            return;
        }

        $session = $request->getSession();
        $session->set(self::SESSION_KEY_REFERRAL_CODE, $referralCode);
    }

    public function createTrackingRecords(EntityWrittenContainerEvent $event): void
    {
        $orderEvent = $event->getEventByEntityName(OrderDefinition::ENTITY_NAME);
        $customerEvent = $event->getEventByEntityName(CustomerDefinition::ENTITY_NAME);

        if ($orderEvent === null && $customerEvent === null) {
            return;
        }

        $referralCode = $this->resolveReferralCode($event);

        if ($referralCode === null) {
            return;
        }

        if ($orderEvent) {
            $this->trackOrders($orderEvent, $event->getContext(), $referralCode);
        }

        if ($customerEvent) {
            $this->trackCustomers($customerEvent, $event->getContext(), $referralCode);
        }
    }

    public function invalidateTrackableChannelCache(EntityWrittenEvent $event): void
    {
        $tags = array_map(
            static fn (string $id): string => self::CACHE_KEY_PREFIX . $id,
            $event->getIds(),
        );

        $this->cache->invalidateTags($tags);
    }

    private function resolveReferralCode(EntityWrittenContainerEvent $event): ?string
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return null;
        }

        $request = $this->getCurrentRequest();

        if ($request === null || !$request->hasSession(true)) {
            return null;
        }

        $session = $request->getSession();

        if (!$session->isStarted()) {
            return null;
        }

        $referralCode = $session->get(self::SESSION_KEY_REFERRAL_CODE);

        if (!\is_string($referralCode) || $referralCode === '') {
            return null;
        }

        return $referralCode;
    }

    private function trackOrders(EntityWrittenEvent $orderEvent, Context $context, string $referralCode): void
    {
        $inserts = $this->filterInserts($orderEvent->getWriteResults());

        if ($inserts === []) {
            return;
        }

        $data = array_map(static function (EntityWriteResult $result) use ($referralCode): array {
            $pk = $result->getPrimaryKey();

            return [
                'id' => Uuid::randomHex(),
                'orderId' => \is_array($pk) ? (string) $pk['id'] : $pk,
                'orderVersionId' => Defaults::LIVE_VERSION,
                'salesChannelId' => $referralCode,
            ];
        }, $inserts);

        try {
            $this->salesChannelTrackingOrderRepository->upsert(array_values($data), $context);
        } catch (\Throwable $e) {
            $this->logger->warning('Sales channel tracking: failed to write order tracking record', [
                'exception' => $e->getMessage(),
                'salesChannelId' => $referralCode,
            ]);
        }
    }

    private function trackCustomers(EntityWrittenEvent $customerEvent, Context $context, string $referralCode): void
    {
        $inserts = $this->filterInserts($customerEvent->getWriteResults());

        if ($inserts === []) {
            return;
        }

        $data = array_map(static function (EntityWriteResult $result) use ($referralCode): array {
            $pk = $result->getPrimaryKey();

            return [
                'id' => Uuid::randomHex(),
                'customerId' => \is_array($pk) ? (string) $pk['id'] : $pk,
                'salesChannelId' => $referralCode,
            ];
        }, $inserts);

        try {
            $this->salesChannelTrackingCustomerRepository->upsert(array_values($data), $context);
        } catch (\Throwable $e) {
            $this->logger->warning('Sales channel tracking: failed to write customer tracking record', [
                'exception' => $e->getMessage(),
                'salesChannelId' => $referralCode,
            ]);
        }
    }

    /**
     * @param list<EntityWriteResult> $results
     *
     * @return list<EntityWriteResult>
     */
    private function filterInserts(array $results): array
    {
        return array_values(array_filter(
            $results,
            static fn (EntityWriteResult $r): bool => $r->getOperation() === EntityWriteResult::OPERATION_INSERT,
        ));
    }

    private function isTrackableChannel(string $salesChannelId): bool
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $salesChannelId;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($salesChannelId): bool {
            $item->tag(self::CACHE_KEY_PREFIX . $salesChannelId);

            $criteria = new Criteria([$salesChannelId]);
            $criteria->addFilter(new EqualsAnyFilter('typeId', self::TRACKABLE_TYPE_IDS));
            $criteria->setLimit(1);

            return $this->salesChannelRepository->searchIds($criteria, Context::createDefaultContext())->getTotal() > 0;
        });
    }

    private function getCurrentRequest(): ?Request
    {
        return $this->requestStack->getMainRequest();
    }
}

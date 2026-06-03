<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Event\PromotionCodeRedeemedEvent;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionIndividualCodeRedeemer implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<PromotionIndividualCodeCollection> $codesRepository
     * @param EntityRepository<OrderCustomerCollection> $orderCustomerRepository
     */
    public function __construct(
        private readonly EntityRepository $codesRepository,
        private readonly EntityRepository $orderCustomerRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT => 'onOrderLineItemWritten',
        ];
    }

    public function onOrderLineItemWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $orderLineItems = $this->collectLineItems($event);

        if ($orderLineItems->count() === 0) {
            return;
        }

        // one write can carry promotion line items for several orders (sync/import), so
        // resolve the customer per order rather than reusing the first order's customer
        $orderCustomers = $this->getOrderCustomers($orderLineItems, $event);

        $this->redeemCode($orderLineItems, $orderCustomers, $event->getContext());
    }

    /**
     * @param array<string, OrderCustomerEntity> $orderCustomers
     */
    private function redeemCode(OrderLineItemCollection $lineItems, array $orderCustomers, Context $context): void
    {
        $update = [];
        $redeemedEvents = [];
        $codes = \array_values(\array_filter(\array_map(
            static fn ($item) => $item->getPayload()['code'] ?? '',
            \iterator_to_array($lineItems)
        )));

        if ($codes === []) {
            return;
        }

        $promotions = $this->getIndividualCodePromotions($codes, $context);

        foreach ($lineItems as $item) {
            $customer = $orderCustomers[$item->getOrderId()] ?? null;
            if ($customer === null) {
                continue;
            }

            foreach ($promotions as $promotion) {
                /** @var string $code */
                $code = $item->getPayload()['code'] ?? '';

                if (strtolower($code) !== strtolower($promotion->getCode())) {
                    continue;
                }

                // a code already carrying an orderId is redeemed; re-writing the same
                // order's line items (order edits, sync, recalculation) must not re-fire
                // the redemption event (isRedeemed() is not a state signal — it always
                // returns true — so the payload is the source of truth)
                $payload = $promotion->getPayload();
                $alreadyRedeemed = $payload !== null && \array_key_exists('orderId', $payload);

                $promotion->setRedeemed(
                    $item->getOrderId(),
                    $customer->getCustomerId() ?? '',
                    $customer->getFirstName() . ' ' . $customer->getLastName()
                );

                // save in database
                $update[] = [
                    'id' => $promotion->getId(),
                    'payload' => $promotion->getPayload(),
                ];

                if ($alreadyRedeemed) {
                    continue;
                }

                // one code can attach to several promotion line items (one per
                // discount), so key by code id to emit exactly one redemption event —
                // matching the upsert-by-id idempotency the $update path relies on
                $redeemedEvents[$promotion->getId()] = new PromotionCodeRedeemedEvent(
                    $context,
                    $promotion->getPromotionId(),
                    $promotion->getId(),
                    $promotion->getCode(),
                    $item->getOrderId(),
                    $customer->getCustomerId()
                );
            }
        }

        if ($update !== []) {
            $this->codesRepository->update($update, $context);
        }

        foreach ($redeemedEvents as $redeemedEvent) {
            $this->eventDispatcher->dispatch($redeemedEvent);
        }
    }

    /**
     * @param list<string> $codes
     */
    private function getPromotions(array $codes, Context $context): PromotionIndividualCodeCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('code', $codes));

        $promotions = $this->codesRepository->search($criteria, $context)->getEntities();
        if ($promotions->count() === 0) {
            throw PromotionException::promotionCodesNotFound($codes);
        }

        return $promotions;
    }

    private function collectLineItems(EntityWrittenEvent $event): OrderLineItemCollection
    {
        $orderLineItems = new OrderLineItemCollection();

        foreach ($event->getWriteResults() as $result) {
            if (($result->getPayload()['type'] ?? '') !== PromotionProcessor::LINE_ITEM_TYPE) {
                continue;
            }
            $orderLineItems->add((new OrderLineItemEntity())->assign($result->getPayload()));
        }

        return $orderLineItems;
    }

    /**
     * @return array<string, OrderCustomerEntity> keyed by order id
     */
    private function getOrderCustomers(OrderLineItemCollection $orderLineItems, EntityWrittenEvent $event): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map(
            static fn (OrderLineItemEntity $item): string => $item->getOrderId(),
            $orderLineItems->getElements()
        ))));

        if ($orderIds === []) {
            return [];
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('orderId', $orderIds));

        $orderCustomers = [];
        foreach ($this->orderCustomerRepository->search($criteria, $event->getContext())->getEntities() as $orderCustomer) {
            $orderCustomers[$orderCustomer->getOrderId()] = $orderCustomer;
        }

        return $orderCustomers;
    }

    /**
     * @param list<string> $codes
     */
    private function getIndividualCodePromotions(array $codes, Context $context): PromotionIndividualCodeCollection
    {
        try {
            $promotions = $this->getPromotions($codes, $context);
        } catch (PromotionException) {
            $promotions = new PromotionIndividualCodeCollection();
        }

        return $promotions;
    }
}

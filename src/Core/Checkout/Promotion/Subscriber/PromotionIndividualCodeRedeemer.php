<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
        private readonly EntityRepository $orderCustomerRepository
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

        $orderCustomer = $this->getOrderCustomer($orderLineItems, $event);

        $this->redeemCode($orderLineItems, $orderCustomer, $event->getContext());
    }

    private function redeemCode(OrderLineItemCollection $lineItems, OrderCustomerEntity $customer, Context $context): void
    {
        $update = [];
        $codes = \array_values(\array_filter(\array_map(
            fn ($item) => $item->getPayload()['code'] ?? '',
            \iterator_to_array($lineItems)
        )));

        if (empty($codes)) {
            return;
        }

        $promotions = $this->getIndividualCodePromotions($codes, $context);

        foreach ($lineItems as $item) {
            foreach ($promotions as $promotion) {
                /** @var string $code */
                $code = $item->getPayload()['code'] ?? '';

                if ($code !== $promotion->getCode()) {
                    continue;
                }

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
            }
        }

        if (!empty($update)) {
            $this->codesRepository->update($update, $context);
        }
    }

    /**
     * @param list<string> $codes
     */
    private function getPromotions(array $codes, Context $context): PromotionIndividualCodeCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('code', $codes)
        );

        $promotions = $this->codesRepository->search($criteria, $context)->getEntities();

        if (!($promotions instanceof PromotionIndividualCodeCollection) || $promotions->count() === 0) {
            throw PromotionException::promotionCodesNotFound($codes);
        }

        return $promotions;
    }

    private function collectLineItems(EntityWrittenEvent $event): OrderLineItemCollection
    {
        $orderLineItems = new OrderLineItemCollection();

        foreach ($event->getWriteResults() as $result) {
            if ($result->getPayload()['type'] !== PromotionProcessor::LINE_ITEM_TYPE) {
                continue;
            }
            $orderLineItems->add((new OrderLineItemEntity())->assign($result->getPayload()));
        }

        return $orderLineItems;
    }

    private function getOrderCustomer(OrderLineItemCollection $orderLineItems, EntityWrittenEvent $event): OrderCustomerEntity
    {
        $lineItem = $orderLineItems->first();
        \assert($lineItem instanceof OrderLineItemEntity);

        $orderCustomer = $this->orderCustomerRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('orderId', $lineItem->getOrderId())),
            $event->getContext()
        )->getEntities()->first();
        \assert($orderCustomer instanceof OrderCustomerEntity);

        return $orderCustomer;
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

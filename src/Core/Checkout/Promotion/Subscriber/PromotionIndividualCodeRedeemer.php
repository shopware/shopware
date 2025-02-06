<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    private function redeemCode(?OrderLineItemCollection $lineItems, OrderCustomerEntity $customer, Context $context): void
    {
        foreach ($lineItems ?? [] as $item) {
            // only update promotions in here
            if ($item->getType() !== PromotionProcessor::LINE_ITEM_TYPE) {
                continue;
            }

            /** @var string $code */
            $code = $item->getPayload()['code'] ?? '';

            try {
                // first try if it's an individual
                // if not, then it might be a global promotion
                $individualCode = $this->getIndividualCode($code, $context);
            } catch (PromotionException) {
                $individualCode = null;
            }

            // if we did not use an individual code we might have
            // just used a global one or anything else, so just continue in this case
            // and go on with the next promotion if any are left in the collection
            if (!($individualCode instanceof PromotionIndividualCodeEntity)) {
                continue;
            }

            // set the code to be redeemed
            // and assign all required metadata
            // for later needs
            $individualCode->setRedeemed(
                $item->getOrderId(),
                $customer->getCustomerId() ?? '',
                $customer->getFirstName() . ' ' . $customer->getLastName()
            );

            // save in database
            $this->codesRepository->update(
                [
                    [
                        'id' => $individualCode->getId(),
                        'payload' => $individualCode->getPayload(),
                    ],
                ],
                $context
            );
        }
    }

    private function getIndividualCode(string $code, Context $context): PromotionIndividualCodeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('code', $code)
        );

        /** @var PromotionIndividualCodeEntity|null $promotion */
        $promotion = $this->codesRepository->search($criteria, $context)->first();

        if (!$promotion) {
            throw PromotionException::promotionCodeNotFound($code);
        }

        return $promotion;
    }

    private function collectLineItems(EntityWrittenEvent $event): OrderLineItemCollection
    {
        $orderLineItems = new OrderLineItemCollection();

        foreach ($event->getWriteResults() as $result) {
            if ($result->getPayload()['type'] !== 'promotion') {
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
}

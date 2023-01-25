<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Exception\CodeAlreadyRedeemedException;
use Shopware\Core\Checkout\Promotion\Exception\PromotionCodeNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
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
     */
    public function __construct(private readonly EntityRepository $codesRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    /**
     * @throws CodeAlreadyRedeemedException
     * @throws InconsistentCriteriaIdsException
     */
    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        foreach ($event->getOrder()->getLineItems() ?? [] as $item) {
            // only update promotions in here
            if ($item->getType() !== PromotionProcessor::LINE_ITEM_TYPE) {
                continue;
            }

            /** @var string $code */
            $code = $item->getPayload()['code'] ?? '';

            try {
                // first try if its an individual
                // if not, then it might be a global promotion
                $individualCode = $this->getIndividualCode($code, $event->getContext());
            } catch (PromotionCodeNotFoundException) {
                $individualCode = null;
            }

            // if we did not use an individual code we might have
            // just used a global one or anything else, so just quit in this case.
            if (!($individualCode instanceof PromotionIndividualCodeEntity)) {
                return;
            }

            /** @var OrderCustomerEntity $customer */
            $customer = $event->getOrder()->getOrderCustomer();

            // set the code to be redeemed
            // and assign all required meta data
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
                $event->getContext()
            );
        }
    }

    /**
     * Gets all individual code entities for the provided code value.
     *
     * @throws PromotionCodeNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    private function getIndividualCode(string $code, Context $context): PromotionIndividualCodeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('code', $code)
        );

        /** @var PromotionIndividualCodeCollection $result */
        $result = $this->codesRepository->search($criteria, $context)->getEntities();

        if (\count($result->getElements()) <= 0) {
            throw new PromotionCodeNotFoundException($code);
        }

        // return first element
        return $result->first();
    }
}

<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Exception\PromotionCodeNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PromotionIndividualCodeRedeemer implements EventSubscriberInterface
{
    /** @var EntityRepositoryInterface */
    private $codesRepository;

    public function __construct(EntityRepositoryInterface $codesRepository)
    {
        $this->codesRepository = $codesRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    /**
     * @throws \Shopware\Core\Checkout\Promotion\Exception\CodeAlreadyRedeemedException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        foreach ($event->getOrder()->getLineItems() as $item) {
            // only update promotions in here
            if ($item->getType() !== PromotionProcessor::LINE_ITEM_TYPE) {
                continue;
            }

            /** @var string $code */
            $code = $item->getPayload()['code'];

            /** @var PromotionIndividualCodeEntity|null $individualCode */
            $individualCode = null;

            try {
                // first try if its an individual
                // if not, then it might be a global promotion
                $individualCode = $this->getIndividualCode($code, $event->getContext());
            } catch (PromotionCodeNotFoundException $ex) {
                $individualCode = null;
            }

            // if we did not use an individual code we might have
            // just used a global one or anything else, so just quit in this case.
            if (!$individualCode instanceof PromotionIndividualCodeEntity) {
                return;
            }

            /** @var OrderCustomerEntity $customer */
            $customer = $event->getOrder()->getOrderCustomer();

            // set the code to be redeemed
            // and assign all required meta data
            // for later needs
            $individualCode->setRedeemed(
                $item->getOrderId(),
                $customer->getCustomerId(),
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
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getIndividualCode(string $code, Context $context): PromotionIndividualCodeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('code', $code)
        );

        /** @var PromotionIndividualCodeCollection $result */
        $result = $this->codesRepository->search($criteria, $context)->getEntities();

        if (count($result->getElements()) <= 0) {
            throw new PromotionCodeNotFoundException($code);
        }

        // return first element
        return $result->first();
    }
}

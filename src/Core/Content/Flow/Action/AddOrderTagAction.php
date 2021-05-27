<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\OrderAware;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class AddOrderTagAction extends FlowAction
{
    private EntityRepositoryInterface $orderTagRepository;

    public function __construct(EntityRepositoryInterface $orderTagRepository)
    {
        $this->orderTagRepository = $orderTagRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            FlowAction::ADD_ORDER_TAG => 'addTag',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function addTag(BusinessEvent $event): void
    {
        /** @var CheckoutOrderPlacedEvent $checkoutOrderPlacedEvent */
        $checkoutOrderPlacedEvent = $event->getEvent();
        $this->orderTagRepository->create([
            [
                'orderId' => $checkoutOrderPlacedEvent->getOrderId(),
                'orderVersionId' => $checkoutOrderPlacedEvent->getOrder()->getVersionId(),
                'tagId' => $event->getConfig()['tagId'],
            ],
        ], $checkoutOrderPlacedEvent->getContext());
    }
}

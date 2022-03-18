<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;

class SetOrderCustomFieldAction extends FlowAction
{
    use CustomFieldActionTrait;

    private Connection $connection;

    private EntityRepositoryInterface $orderRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $orderRepository
    ) {
        $this->connection = $connection;
        $this->orderRepository = $orderRepository;
    }

    public static function getName(): string
    {
        return 'action.set.order.custom.field';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        $baseEvent = $event->getEvent();
        if (!$baseEvent instanceof OrderAware) {
            return;
        }

        $config = $event->getConfig();
        $orderId = $baseEvent->getOrderId();

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search(new Criteria([$orderId]), $baseEvent->getContext())->first();

        $customFields = $this->getCustomFieldForUpdating($order->getCustomfields(), $config);

        if ($customFields === null) {
            return;
        }

        $customFields = empty($customFields) ? null : $customFields;

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'customFields' => $customFields,
            ],
        ], $baseEvent->getContext());
    }
}

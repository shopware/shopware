<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;

/**
 * @package business-ops
 *
 * @internal
 *
 * @deprecated tag:v6.5.0 - reason:remove-subscriber - FlowActions won't be executed over the event system anymore,
 * therefore the actions won't implement the EventSubscriberInterface anymore.
 */
class SetOrderCustomFieldAction extends FlowAction implements DelayableAction
{
    use CustomFieldActionTrait;

    private Connection $connection;

    private EntityRepository $orderRepository;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        EntityRepository $orderRepository
    ) {
        $this->connection = $connection;
        $this->orderRepository = $orderRepository;
    }

    public static function getName(): string
    {
        return 'action.set.order.custom.field';
    }

    /**
     * @deprecated tag:v6.5.0 - reason:remove-subscriber - Will be removed
     */
    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('v6.5.0.0')) {
            return [];
        }

        return [
            self::getName() => 'handle',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [OrderAware::class];
    }

    /**
     * @deprecated tag:v6.5.0 Will be removed, implement handleFlow instead
     */
    public function handle(FlowEvent $event): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $baseEvent = $event->getEvent();
        if (!$baseEvent instanceof OrderAware) {
            return;
        }

        $this->update($baseEvent->getContext(), $event->getConfig(), $baseEvent->getOrderId());
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasStore(OrderAware::ORDER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getStore(OrderAware::ORDER_ID));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $orderId): void
    {
        /** @var OrderEntity $order */
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();

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
        ], $context);
    }
}

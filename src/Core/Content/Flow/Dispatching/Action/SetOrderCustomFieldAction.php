<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('business-ops')]
class SetOrderCustomFieldAction extends FlowAction implements DelayableAction
{
    use CustomFieldActionTrait;

    private readonly Connection $connection;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        private readonly EntityRepository $orderRepository
    ) {
        $this->connection = $connection;
    }

    public static function getName(): string
    {
        return 'action.set.order.custom.field';
    }

    /**
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(OrderAware::ORDER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(OrderAware::ORDER_ID));
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

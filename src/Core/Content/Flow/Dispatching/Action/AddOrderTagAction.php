<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\OrderAware;

/**
 * @package business-ops
 *
 * @internal
 */
class AddOrderTagAction extends FlowAction implements DelayableAction
{
    private EntityRepository $orderRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public static function getName(): string
    {
        return 'action.add.order.tag';
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
        if (!\array_key_exists('tagIds', $config) || empty(array_keys($config['tagIds']))) {
            return;
        }

        $tagIds = array_keys($config['tagIds']);

        $tags = array_map(static function ($tagId) {
            return ['id' => $tagId];
        }, $tagIds);

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'tags' => $tags,
            ],
        ], $context);
    }
}

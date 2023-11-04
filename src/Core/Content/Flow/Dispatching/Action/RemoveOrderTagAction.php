<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('business-ops')]
class RemoveOrderTagAction extends FlowAction implements DelayableAction
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $orderTagRepository)
    {
    }

    public static function getName(): string
    {
        return 'action.remove.order.tag';
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
        if (!\array_key_exists('tagIds', $config)) {
            return;
        }

        $tagIds = array_keys($config['tagIds']);

        if (empty($tagIds)) {
            return;
        }

        $tags = array_map(static fn ($tagId) => [
            'orderId' => $orderId,
            'tagId' => $tagId,
        ], $tagIds);

        $this->orderTagRepository->delete($tags, $context);
    }
}

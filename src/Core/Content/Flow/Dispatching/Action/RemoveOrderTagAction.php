<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;

class RemoveOrderTagAction extends FlowAction
{
    private EntityRepositoryInterface $orderTagRepository;

    public function __construct(EntityRepositoryInterface $orderTagRepository)
    {
        $this->orderTagRepository = $orderTagRepository;
    }

    public static function getName(): string
    {
        return 'action.remove.order.tag';
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
        $config = $event->getConfig();
        if (!\array_key_exists('tagIds', $config)) {
            return;
        }

        $tagIds = array_keys($config['tagIds']);
        $baseEvent = $event->getEvent();

        if (!$baseEvent instanceof OrderAware || empty($tagIds)) {
            return;
        }

        $tags = array_map(static function ($tagId) use ($baseEvent) {
            return [
                'orderId' => $baseEvent->getOrderId(),
                'tagId' => $tagId,
            ];
        }, $tagIds);

        $this->orderTagRepository->delete($tags, $baseEvent->getContext());
    }
}

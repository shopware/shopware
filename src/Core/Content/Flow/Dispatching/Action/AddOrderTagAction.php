<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;

class AddOrderTagAction extends FlowAction
{
    private EntityRepositoryInterface $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public static function getName(): string
    {
        return 'action.add.order.tag';
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

        $tags = array_map(static function ($tagId) {
            return ['id' => $tagId];
        }, $tagIds);

        $this->orderRepository->update([
            [
                'id' => $baseEvent->getOrderId(),
                'tags' => $tags,
            ],
        ], $baseEvent->getContext());
    }
}

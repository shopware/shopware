<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEvent;

class AddCustomerTagAction extends FlowAction
{
    private EntityRepositoryInterface $customerRepository;

    public function __construct(EntityRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public static function getName(): string
    {
        return 'action.add.customer.tag';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [CustomerAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        $config = $event->getConfig();
        if (!\array_key_exists('tagIds', $config)) {
            return;
        }

        $tagIds = array_keys($config['tagIds']);
        $baseEvent = $event->getEvent();

        if (!$baseEvent instanceof CustomerAware || empty($tagIds)) {
            return;
        }

        $tags = array_map(static function ($tagId) {
            return ['id' => $tagId];
        }, $tagIds);

        $this->customerRepository->update([
            [
                'id' => $baseEvent->getCustomerId(),
                'tags' => $tags,
            ],
        ], $baseEvent->getContext());
    }
}

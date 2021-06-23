<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEvent;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class AddCustomerTagAction extends FlowAction
{
    private EntityRepositoryInterface $customerRepository;

    public function __construct(EntityRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function getName(): string
    {
        return FlowAction::ADD_CUSTOMER_TAG;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlowAction::ADD_CUSTOMER_TAG => 'handle',
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

        $tagIds = $config['tagIds'];
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

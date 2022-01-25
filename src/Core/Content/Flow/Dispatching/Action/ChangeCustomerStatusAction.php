<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEvent;

class ChangeCustomerStatusAction extends FlowAction
{
    private EntityRepositoryInterface $customerRepository;

    public function __construct(EntityRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public static function getName(): string
    {
        return 'action.change.customer.status';
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
        if (!\array_key_exists('active', $config)) {
            return;
        }

        $active = $config['active'];
        $baseEvent = $event->getEvent();

        if (!$baseEvent instanceof CustomerAware || !\is_bool($active)) {
            return;
        }

        $this->customerRepository->update([
            [
                'id' => $baseEvent->getCustomerId(),
                'active' => $active,
            ],
        ], $baseEvent->getContext());
    }
}

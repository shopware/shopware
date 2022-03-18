<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\FlowEvent;

class SetCustomerCustomFieldAction extends FlowAction
{
    use CustomFieldActionTrait;

    private Connection $connection;

    private EntityRepositoryInterface $customerRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $customerRepository
    ) {
        $this->connection = $connection;
        $this->customerRepository = $customerRepository;
    }

    public static function getName(): string
    {
        return 'action.set.customer.custom.field';
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
        $baseEvent = $event->getEvent();
        if (!$baseEvent instanceof CustomerAware) {
            return;
        }

        $config = $event->getConfig();
        $customerId = $baseEvent->getCustomerId();

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $baseEvent->getContext())->first();

        $customFields = $this->getCustomFieldForUpdating($customer->getCustomfields(), $config);

        if ($customFields === null) {
            return;
        }

        $customFields = empty($customFields) ? null : $customFields;

        $this->customerRepository->update([
            [
                'id' => $customerId,
                'customFields' => $customFields,
            ],
        ], $baseEvent->getContext());
    }
}

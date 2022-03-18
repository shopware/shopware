<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\CustomerGroupAware;
use Shopware\Core\Framework\Event\FlowEvent;

class SetCustomerGroupCustomFieldAction extends FlowAction
{
    use CustomFieldActionTrait;

    private Connection $connection;

    private EntityRepositoryInterface $customerGroupRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $customerGroupRepository
    ) {
        $this->connection = $connection;
        $this->customerGroupRepository = $customerGroupRepository;
    }

    public static function getName(): string
    {
        return 'action.set.customer.group.custom.field';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    public function requirements(): array
    {
        return [CustomerGroupAware::class];
    }

    public function handle(FlowEvent $event): void
    {
        $baseEvent = $event->getEvent();
        if (!$baseEvent instanceof CustomerGroupAware) {
            return;
        }

        $config = $event->getConfig();
        $customerGroupId = $baseEvent->getCustomerGroupId();

        /** @var CustomerGroupEntity $customerGroup */
        $customerGroup = $this->customerGroupRepository->search(new Criteria([$customerGroupId]), $baseEvent->getContext())->first();

        $customFields = $this->getCustomFieldForUpdating($customerGroup->getCustomfields(), $config);

        if ($customFields === null) {
            return;
        }

        $customFields = empty($customFields) ? null : $customFields;

        $this->customerGroupRepository->update([
            [
                'id' => $customerGroupId,
                'customFields' => $customFields,
            ],
        ], $baseEvent->getContext());
    }
}

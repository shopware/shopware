<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\CustomerGroupAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('business-ops')]
class SetCustomerGroupCustomFieldAction extends FlowAction implements DelayableAction
{
    use CustomFieldActionTrait;

    private readonly Connection $connection;

    /**
     * @internal
     */
    public function __construct(
        Connection $connection,
        private readonly EntityRepository $customerGroupRepository
    ) {
        $this->connection = $connection;
    }

    public static function getName(): string
    {
        return 'action.set.customer.group.custom.field';
    }

    /**
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [CustomerGroupAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(CustomerGroupAware::CUSTOMER_GROUP_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(CustomerGroupAware::CUSTOMER_GROUP_ID));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $customerGroupId): void
    {
        /** @var CustomerGroupEntity $customerGroup */
        $customerGroup = $this->customerGroupRepository->search(new Criteria([$customerGroupId]), $context)->first();

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
        ], $context);
    }
}

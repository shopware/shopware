<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('business-ops')]
class ChangeCustomerGroupAction extends FlowAction implements DelayableAction
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $customerRepository)
    {
    }

    public static function getName(): string
    {
        return 'action.change.customer.group';
    }

    /**
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [CustomerAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(CustomerAware::CUSTOMER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(CustomerAware::CUSTOMER_ID));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $customerId): void
    {
        if (!\array_key_exists('customerGroupId', $config)) {
            return;
        }

        $customerGroupId = $config['customerGroupId'];
        if (empty($customerGroupId)) {
            return;
        }

        $this->customerRepository->update([
            [
                'id' => $customerId,
                'groupId' => $customerGroupId,
            ],
        ], $context);
    }
}

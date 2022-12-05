<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\CustomerAware;

/**
 * @package business-ops
 *
 * @internal
 */
class ChangeCustomerGroupAction extends FlowAction implements DelayableAction
{
    private EntityRepository $customerRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
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
        if (!$flow->hasStore(CustomerAware::CUSTOMER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getStore(CustomerAware::CUSTOMER_ID));
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

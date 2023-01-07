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
class RemoveCustomerTagAction extends FlowAction implements DelayableAction
{
    private EntityRepository $customerTagRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepository $customerTagRepository)
    {
        $this->customerTagRepository = $customerTagRepository;
    }

    public static function getName(): string
    {
        return 'action.remove.customer.tag';
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
        if (!\array_key_exists('tagIds', $config)) {
            return;
        }

        $tagIds = array_keys($config['tagIds']);

        if (empty($tagIds)) {
            return;
        }

        $tags = array_map(static function ($tagId) use ($customerId) {
            return [
                'customerId' => $customerId,
                'tagId' => $tagId,
            ];
        }, $tagIds);

        $this->customerTagRepository->delete($tags, $context);
    }
}

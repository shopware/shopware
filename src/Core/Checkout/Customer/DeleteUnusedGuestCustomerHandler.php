<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: DeleteUnusedGuestCustomerTask::class)]
#[Package('customer-order')]
final class DeleteUnusedGuestCustomerHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly DeleteUnusedGuestCustomerService $unusedGuestCustomerService
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $this->unusedGuestCustomerService->deleteUnusedCustomers(Context::createDefaultContext());
    }
}

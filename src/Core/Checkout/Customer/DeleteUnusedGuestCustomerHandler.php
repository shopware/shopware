<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @package customer-order
 *
 * @internal
 */
#[AsMessageHandler(handles: DeleteUnusedGuestCustomerTask::class)]
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

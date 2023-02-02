<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class DeleteUnusedGuestCustomerHandler extends ScheduledTaskHandler
{
    private DeleteUnusedGuestCustomerService $unusedGuestCustomerService;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        DeleteUnusedGuestCustomerService $unusedGuestCustomerService
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->unusedGuestCustomerService = $unusedGuestCustomerService;
    }

    public function run(): void
    {
        $this->unusedGuestCustomerService->deleteUnusedCustomers(Context::createDefaultContext());
    }

    public static function getHandledMessages(): iterable
    {
        return [DeleteUnusedGuestCustomerTask::class];
    }
}

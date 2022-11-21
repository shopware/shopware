<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - MessageHandler will be internal and final starting with v6.5.0.0
 */
class DeleteUnusedGuestCustomerHandler extends ScheduledTaskHandler
{
    private DeleteUnusedGuestCustomerService $unusedGuestCustomerService;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
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

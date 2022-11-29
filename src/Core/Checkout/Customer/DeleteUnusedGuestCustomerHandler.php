<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @package customer-order
 *
 * @internal
 */
final class DeleteUnusedGuestCustomerHandler extends ScheduledTaskHandler
{
    private DeleteUnusedGuestCustomerService $unusedGuestCustomerService;

    /**
     * @internal
     */
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

    /**
     * @return iterable<class-string<ScheduledTask>>
     */
    public static function getHandledMessages(): iterable
    {
        return [DeleteUnusedGuestCustomerTask::class];
    }
}

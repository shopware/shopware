<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase\Handler;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Store\InAppPurchase\InAppPurchaseSyncTask;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchasesSyncService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 *  @internal
 */
#[AsMessageHandler(handles: InAppPurchaseSyncTask::class)]
#[Package('checkout')]
final class InAppPurchaseSyncHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly InAppPurchasesSyncService $iapSyncService
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        try {
            $this->iapSyncService->disableExpiredInAppPurchases();
            $this->iapSyncService->updateActiveInAppPurchases($context);
        } catch (\Exception $e) {
            $this->exceptionLogger?->error($e->getMessage(), ['exception' => $e]);
        }
    }
}

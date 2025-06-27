<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase\Handler;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Store\InAppPurchase\InAppPurchaseUpdateTask;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[AsMessageHandler(handles: InAppPurchaseUpdateTask::class)]
#[Package('checkout')]
final class InAppPurchaseUpdateHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly InAppPurchaseUpdater $iapUpdater
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createCLIContext();
        $this->iapUpdater->update($context);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Shopware\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @package core
 *
 * @internal
 */
#[AsMessageHandler(handles: UpdateAppsTask::class)]
final class UpdateAppsHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(EntityRepository $scheduledTaskRepository, private AbstractAppUpdater $appUpdater)
    {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $this->appUpdater->updateApps(Context::createDefaultContext());
    }
}

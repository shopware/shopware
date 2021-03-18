<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Shopware\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class UpdateAppsHandler extends ScheduledTaskHandler
{
    private ?AbstractAppUpdater $appUpdater;

    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, ?AbstractAppUpdater $appUpdater)
    {
        parent::__construct($scheduledTaskRepository);
        $this->appUpdater = $appUpdater;
    }

    public function run(): void
    {
        if (!$this->appUpdater) {
            return;
        }
        $this->appUpdater->updateApps(Context::createDefaultContext());
    }

    public static function getHandledMessages(): iterable
    {
        return [UpdateAppsTask::class];
    }
}

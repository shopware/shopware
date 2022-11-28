<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Shopware\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @package core
 *
 * @internal
 */
final class UpdateAppsHandler extends ScheduledTaskHandler
{
    private AbstractAppUpdater $appUpdater;

    /**
     * @internal
     */
    public function __construct(EntityRepository $scheduledTaskRepository, AbstractAppUpdater $appUpdater)
    {
        parent::__construct($scheduledTaskRepository);
        $this->appUpdater = $appUpdater;
    }

    public function run(): void
    {
        $this->appUpdater->updateApps(Context::createDefaultContext());
    }

    /**
     * @return iterable<class-string<ScheduledTask>>
     */
    public static function getHandledMessages(): iterable
    {
        return [UpdateAppsTask::class];
    }
}

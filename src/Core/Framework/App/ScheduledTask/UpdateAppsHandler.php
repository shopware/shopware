<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Shopware\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - MessageHandler will be internal and final starting with v6.5.0.0
 */
class UpdateAppsHandler extends ScheduledTaskHandler
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

    public static function getHandledMessages(): iterable
    {
        return [UpdateAppsTask::class];
    }
}

<?php declare(strict_types=1);

namespace SwagTest;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @final
 *
 * @internal
 */
class SwagTestTaskHandler extends ScheduledTaskHandler
{
    /**
     * @return iterable<class-string<ScheduledTask>>
     */
    public static function getHandledMessages(): iterable
    {
        return [SwagTestTask::class];
    }

    public function run(): void
    {
    }
}

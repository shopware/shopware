<?php declare(strict_types=1);

namespace SwagTest;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class SwagTestTaskHandler extends ScheduledTaskHandler
{
    public static function getHandledMessages(): iterable
    {
        return [SwagTestTask::class];
    }

    public function run(): void
    {
    }
}

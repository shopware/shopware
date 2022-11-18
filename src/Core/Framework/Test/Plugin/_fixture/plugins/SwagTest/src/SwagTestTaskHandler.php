<?php declare(strict_types=1);

namespace SwagTest;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - MessageHandler will be internal and final starting with v6.5.0.0
 */
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

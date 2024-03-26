<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\UseCLIContextRule;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 */
final class TaskHandler extends ScheduledTaskHandler
{
    public function run(): void
    {
        Context::createDefaultContext();
    }
}

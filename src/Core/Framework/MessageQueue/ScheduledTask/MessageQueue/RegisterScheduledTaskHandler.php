<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\MessageQueue;

use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;

class RegisterScheduledTaskHandler extends AbstractMessageHandler
{
    /**
     * @var TaskRegistry
     */
    private $registry;

    public function __construct(TaskRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function handle($message): void
    {
        $this->registry->registerTasks();
    }

    public static function getHandledMessages(): iterable
    {
        return [RegisterScheduledTaskMessage::class];
    }
}

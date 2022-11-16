<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\MessageQueue;

use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will only implement MessageHandlerInterface and all MessageHandler will be internal and final starting with v6.5.0.0
 */
class RegisterScheduledTaskHandler extends AbstractMessageHandler
{
    /**
     * @var TaskRegistry
     */
    private $registry;

    /**
     * @internal
     */
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

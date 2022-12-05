<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\MessageQueue;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

/**
 * @package core
 *
 * @final
 *
 * @internal
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will only implement MessageHandlerInterface and all MessageHandler will be internal and final starting with v6.5.0.0
 */
class RegisterScheduledTaskHandler implements MessageSubscriberInterface
{
    private TaskRegistry $registry;

    /**
     * @internal
     */
    public function __construct(TaskRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function __invoke(RegisterScheduledTaskMessage $message): void
    {
        $this->registry->registerTasks();
    }

    /**
     * @return iterable<class-string<AsyncMessageInterface>>
     */
    public static function getHandledMessages(): iterable
    {
        return [RegisterScheduledTaskMessage::class];
    }
}

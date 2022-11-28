<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class UpdatePostFinishSubscriber implements EventSubscriberInterface
{
    private TaskRegistry $registry;

    /**
     * @internal
     */
    public function __construct(TaskRegistry $taskRegistry)
    {
        $this->registry = $taskRegistry;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [UpdatePostFinishEvent::class => 'updatePostFinishEvent'];
    }

    public function updatePostFinishEvent(): void
    {
        $this->registry->registerTasks();
    }
}

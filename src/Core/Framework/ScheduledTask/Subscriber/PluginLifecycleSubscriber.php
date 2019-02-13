<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask\Subscriber;

use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\ScheduledTask\Registry\TaskRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @var TaskRegistry
     */
    private $registry;

    public function __construct(TaskRegistry $registry)
    {
        $this->registry = $registry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::NAME => 'registerScheduledTasked',
            PluginPostDeactivateEvent::NAME => 'registerScheduledTasked',
        ];
    }

    public function registerScheduledTasked(): void
    {
        $this->registry->registerTasks();
    }
}

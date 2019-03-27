<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask\Subscriber;

use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\ScheduledTask\MessageQueue\RegisterScheduledTaskMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
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
        $this->messageBus->dispatch(new RegisterScheduledTaskMessage());
    }
}

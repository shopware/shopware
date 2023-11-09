<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - reason:remove-subscriber - Will be removed, configure your workers to consume the low_priority queue
 */
#[Package('core')]
class ConsumeMessagesSubscriber implements EventSubscriberInterface
{
    public const LOW_PRIORITY_QUEUE = 'low_priority';

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onMessengerConsume',
        ];
    }

    public function onMessengerConsume(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();

        if ($command === null) {
            return;
        }

        if ($command->getName() !== 'messenger:consume') {
            return;
        }

        $receivers = $event->getInput()->getArgument('receivers');

        // If no receivers are specified, let the user interactively choose
        if (\count($receivers) < 1) {
            return;
        }

        if (!\in_array(self::LOW_PRIORITY_QUEUE, $receivers, true)) {
            $receivers[] = self::LOW_PRIORITY_QUEUE;
            $event->getInput()->setArgument('receivers', $receivers);
        }
    }
}

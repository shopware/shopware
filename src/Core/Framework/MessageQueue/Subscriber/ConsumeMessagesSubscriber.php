<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArgvInput;
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

        $input = $event->getInput();
        $receivers = $input->getArgument('receivers');

        if ($input->isInteractive() && $receivers === []) {
            return;
        }

        if (!$input instanceof ArgvInput) {
            return;
        }

        // https://github.com/symfony/symfony/issues/52415
        $reflectionClass = new \ReflectionClass($input);
        $tokens = $reflectionClass->getProperty('tokens');
        $tokens->setAccessible(true);

        if ($receivers === []) {
            $receivers = ['async'];
        }

        if (!\in_array(self::LOW_PRIORITY_QUEUE, $receivers, true) && \in_array('async', $receivers, true)) {
            $tokens->setValue($input, array_merge($tokens->getValue($input), [self::LOW_PRIORITY_QUEUE]));
            $input->setArgument('receivers', array_merge($receivers, [self::LOW_PRIORITY_QUEUE]));
        }
    }
}

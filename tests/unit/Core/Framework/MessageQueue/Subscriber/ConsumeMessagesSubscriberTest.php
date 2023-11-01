<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Subscriber;

use Composer\Console\Input\InputArgument;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Subscriber\ConsumeMessagesSubscriber;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\MessageQueue\Subscriber\ConsumeMessagesSubscriber
 */
#[Package('core')]
class ConsumeMessagesSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            ['console.command' => 'onMessengerConsume'],
            ConsumeMessagesSubscriber::getSubscribedEvents()
        );
    }

    public function testOnlyHandlesConsumeMessagesCommand(): void
    {
        $command = new RandomCommand();

        $event = new ConsoleCommandEvent(
            $command,
            new ArrayInput(['receivers' => ['async']], $command->getDefinition()),
            $this->createMock(OutputInterface::class),
        );

        $subscriber = new ConsumeMessagesSubscriber();
        $subscriber->onMessengerConsume($event);

        static::assertSame(['async'], $event->getInput()->getArgument('receivers'));
    }

    public function testDoesNotAddAsyncLowPriorityQueueIfNoReceiverIsSpecified(): void
    {
        $command = new ConsumeMessagesCommand();

        $event = new ConsoleCommandEvent(
            $command,
            new ArrayInput([], $command->getDefinition()),
            $this->createMock(OutputInterface::class),
        );

        $subscriber = new ConsumeMessagesSubscriber();
        $subscriber->onMessengerConsume($event);

        static::assertSame([], $event->getInput()->getArgument('receivers'));
    }

    public function testAddsAsyncLowPriorityQueueIfAtLeastOneReceiverIsSpecified(): void
    {
        $command = new ConsumeMessagesCommand();
        $event = new ConsoleCommandEvent(
            $command,
            new ArrayInput(['receivers' => ['async']], $command->getDefinition()),
            $this->createMock(OutputInterface::class),
        );

        $subscriber = new ConsumeMessagesSubscriber();
        $subscriber->onMessengerConsume($event);

        static::assertSame(['async', 'low_priority'], $event->getInput()->getArgument('receivers'));
    }
}

/**
 * @internal
 */
class RandomCommand extends Command
{
    protected static $defaultName = 'random:command';

    protected function configure(): void
    {
        $this->addArgument('receivers', InputArgument::IS_ARRAY);
    }
}

/**
 * @internal
 */
class ConsumeMessagesCommand extends Command
{
    protected static $defaultName = 'messenger:consume';

    protected function configure(): void
    {
        $this->addArgument('receivers', InputArgument::IS_ARRAY);
    }
}

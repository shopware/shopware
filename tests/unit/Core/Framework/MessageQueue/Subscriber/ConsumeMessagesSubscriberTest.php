<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Subscriber;

use Composer\Console\Input\InputArgument;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Subscriber\ConsumeMessagesSubscriber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArgvInput;
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

    public function testReturnsIfNoCommandIsGiven(): void
    {
        $event = new ConsoleCommandEvent(
            null,
            new ArrayInput(['receivers' => ['async']]),
            $this->createMock(OutputInterface::class),
        );

        $subscriber = new ConsumeMessagesSubscriber();
        $subscriber->onMessengerConsume($event);

        static::assertEmpty($event->getInput()->getArguments());
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

    public function testDoesNotAddQueueIfIsInteractive(): void
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

    public function testDoesNotAddQueueIfArgsAreNotArgvInput(): void
    {
        $command = new ConsumeMessagesCommand();
        $event = new ConsoleCommandEvent(
            $command,
            new ArrayInput(['receivers' => ['async']], $command->getDefinition()),
            $this->createMock(OutputInterface::class),
        );

        $subscriber = new ConsumeMessagesSubscriber();
        $subscriber->onMessengerConsume($event);

        static::assertSame(['async'], $event->getInput()->getArgument('receivers'));
    }

    public function testAddsAsyncAndLowPriorityQueue(): void
    {
        $command = new ConsumeMessagesCommand();
        $input = new ArgvInput(['receivers' => []], $command->getDefinition());
        $input->setInteractive(false);

        $event = new ConsoleCommandEvent(
            $command,
            $input,
            $this->createMock(OutputInterface::class),
        );

        $subscriber = new ConsumeMessagesSubscriber();
        $subscriber->onMessengerConsume($event);

        static::assertSame([
            'async',
            'low_priority',
        ], $event->getInput()->getArgument('receivers'));
    }

    public function testAddsLowPriorityQueue(): void
    {
        $command = new ConsumeMessagesCommand();
        $input = new ArgvInput(['applicationName', 'receivers' => 'async'], $command->getDefinition());
        $input->setInteractive(false);

        $event = new ConsoleCommandEvent(
            $command,
            $input,
            $this->createMock(OutputInterface::class),
        );

        $subscriber = new ConsumeMessagesSubscriber();
        $subscriber->onMessengerConsume($event);

        static::assertSame([
            'async',
            'low_priority',
        ], $event->getInput()->getArgument('receivers'));
    }

    public function testDoesNotAddLowPriorityQueue(): void
    {
        $command = new ConsumeMessagesCommand();
        $input = new ArgvInput(['applicationName', 'receivers' => 'failed'], $command->getDefinition());
        $input->setInteractive(false);

        $event = new ConsoleCommandEvent(
            $command,
            $input,
            $this->createMock(OutputInterface::class),
        );

        $subscriber = new ConsumeMessagesSubscriber();
        $subscriber->onMessengerConsume($event);

        static::assertSame(['failed'], $event->getInput()->getArgument('receivers'));
    }
}

/**
 * @internal
 */
#[AsCommand(name: 'random:command')]
class RandomCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('receivers', InputArgument::IS_ARRAY);
    }
}

/**
 * @internal
 */
#[AsCommand(name: 'messenger:consume')]
class ConsumeMessagesCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('receivers', InputArgument::IS_ARRAY);
    }
}

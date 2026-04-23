<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Webhook\Subscriber\WebhookConsumeMessagesSubscriber;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @internal
 */
#[CoversClass(WebhookConsumeMessagesSubscriber::class)]
class WebhookConsumeMessagesSubscriberTest extends TestCase
{
    public function testInjectsWebhookDirectlyBeforeAsync(): void
    {
        $event = $this->makeConsumeEvent(['async']);

        Feature::withFeatureDisabled('v6.8.0.0', function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['webhook', 'async'], $event->getInput()->getArgument('receivers'));
    }

    public function testPreservesOtherReceiversAfterAsync(): void
    {
        $event = $this->makeConsumeEvent(['async', 'low_priority']);

        Feature::withFeatureDisabled('v6.8.0.0', function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['webhook', 'async', 'low_priority'], $event->getInput()->getArgument('receivers'));
    }

    public function testNoOpWhenWebhookAlreadyPresent(): void
    {
        $event = $this->makeConsumeEvent(['webhook', 'async']);

        Feature::withFeatureDisabled('v6.8.0.0', function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['webhook', 'async'], $event->getInput()->getArgument('receivers'));
    }

    public function testPrependsWebhookEvenWhenAsyncAbsent(): void
    {
        $event = $this->makeConsumeEvent(['low_priority']);

        Feature::withFeatureDisabled('v6.8.0.0', function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['webhook', 'low_priority'], $event->getInput()->getArgument('receivers'));
    }

    public function testNoOpWhenReceiversEmpty(): void
    {
        $event = $this->makeConsumeEvent([]);

        Feature::withFeatureDisabled('v6.8.0.0', function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame([], $event->getInput()->getArgument('receivers'));
    }

    public function testNoOpForUnrelatedCommands(): void
    {
        $event = $this->makeConsumeEvent(['async'], commandName: 'cache:clear');

        Feature::withFeatureDisabled('v6.8.0.0', function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['async'], $event->getInput()->getArgument('receivers'));
    }

    public function testNoOpWhenQueuesOptionIsSet(): void
    {
        // --queues=X requires every receiver to implement QueueReceiverInterface, which the
        // webhook transport does not. Prepending would crash the worker at startup with a
        // Symfony Messenger RuntimeException.
        $event = $this->makeConsumeEvent(['async'], queues: ['high']);

        Feature::withFeatureDisabled('v6.8.0.0', function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['async'], $event->getInput()->getArgument('receivers'));
    }

    public function testNoOpWhenV680Active(): void
    {
        // v6.8.0 removes auto-injection — operators register the webhook transport in their
        // consume command explicitly. Runtime gate (compile-time would cache a listener that
        // flag flips cannot unregister).
        $event = $this->makeConsumeEvent(['async']);

        Feature::withFeatureEnabled('v6.8.0.0', function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['async'], $event->getInput()->getArgument('receivers'));
    }

    public function testSubscribesUnconditionally(): void
    {
        // Listener registration is static so compile-time caching is stable across flag
        // flips; the runtime gate lives in onMessengerConsume.
        static::assertSame(
            [ConsoleEvents::COMMAND => 'onMessengerConsume'],
            WebhookConsumeMessagesSubscriber::getSubscribedEvents(),
        );
    }

    /**
     * @param list<string> $receivers
     * @param list<string> $queues
     */
    private function makeConsumeEvent(
        array $receivers,
        string $commandName = 'messenger:consume',
        array $queues = [],
    ): ConsoleCommandEvent {
        $command = new Command($commandName);
        $command->setDefinition(new InputDefinition([
            new InputArgument('receivers', InputArgument::IS_ARRAY),
            new InputOption('queues', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '', []),
        ]));

        $inputArgs = ['receivers' => $receivers];
        if ($queues !== []) {
            $inputArgs['--queues'] = $queues;
        }

        return new ConsoleCommandEvent(
            $command,
            new ArrayInput($inputArgs, $command->getDefinition()),
            new NullOutput(),
        );
    }
}

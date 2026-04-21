<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Webhook\Subscriber\WebhookConsumeMessagesSubscriber;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
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

        Feature::fake([], function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['webhook', 'async'], $event->getInput()->getArgument('receivers'));
    }

    public function testPreservesOtherReceiversAfterAsync(): void
    {
        $event = $this->makeConsumeEvent(['async', 'low_priority']);

        Feature::fake([], function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['webhook', 'async', 'low_priority'], $event->getInput()->getArgument('receivers'));
    }

    public function testNoOpWhenWebhookAlreadyPresent(): void
    {
        $event = $this->makeConsumeEvent(['webhook', 'async']);

        Feature::fake([], function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['webhook', 'async'], $event->getInput()->getArgument('receivers'));
    }

    public function testPrependsWebhookEvenWhenAsyncAbsent(): void
    {
        $event = $this->makeConsumeEvent(['low_priority']);

        Feature::fake([], function () use ($event): void {
            (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
        });

        static::assertSame(['webhook', 'low_priority'], $event->getInput()->getArgument('receivers'));
    }

    public function testNoOpWhenReceiversEmpty(): void
    {
        $event = $this->makeConsumeEvent([]);

        (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);

        static::assertSame([], $event->getInput()->getArgument('receivers'));
    }

    public function testNoOpForUnrelatedCommands(): void
    {
        $event = $this->makeConsumeEvent(['async'], 'cache:clear');

        (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);

        static::assertSame(['async'], $event->getInput()->getArgument('receivers'));
    }

    /**
     * @param list<string> $receivers
     */
    private function makeConsumeEvent(array $receivers, string $commandName = 'messenger:consume'): ConsoleCommandEvent
    {
        $command = new Command($commandName);
        $command->setDefinition(new InputDefinition([
            new InputArgument('receivers', InputArgument::IS_ARRAY),
        ]));

        $input = new class($receivers) implements InputInterface {
            /**
             * @var array<string, list<string>>
             */
            private array $arguments;

            /**
             * @param list<string> $receivers
             */
            public function __construct(array $receivers)
            {
                $this->arguments = ['receivers' => $receivers];
            }

            public function getFirstArgument(): ?string
            {
                return null;
            }

            /**
             * @param string|array<string> $values
             */
            public function hasParameterOption(string|array $values, bool $onlyParams = false): bool
            {
                return false;
            }

            /**
             * @param string|array<string> $values
             * @param string|bool|int|float|array<mixed>|null $default
             */
            public function getParameterOption(string|array $values, string|bool|int|float|array|null $default = false, bool $onlyParams = false): mixed
            {
                return $default;
            }

            public function bind(InputDefinition $definition): void
            {
            }

            public function validate(): void
            {
            }

            /**
             * @return array<string, list<string>>
             */
            public function getArguments(): array
            {
                return $this->arguments;
            }

            public function getArgument(string $name): mixed
            {
                return $this->arguments[$name] ?? null;
            }

            public function setArgument(string $name, mixed $value): void
            {
                $this->arguments[$name] = $value;
            }

            public function hasArgument(string $name): bool
            {
                return isset($this->arguments[$name]);
            }

            /**
             * @return array<string, mixed>
             */
            public function getOptions(): array
            {
                return [];
            }

            public function getOption(string $name): mixed
            {
                return null;
            }

            public function setOption(string $name, mixed $value): void
            {
            }

            public function hasOption(string $name): bool
            {
                return false;
            }

            public function isInteractive(): bool
            {
                return false;
            }

            public function setInteractive(bool $interactive): void
            {
            }

            public function __toString(): string
            {
                return '';
            }
        };

        return new ConsoleCommandEvent($command, $input, new NullOutput());
    }
}

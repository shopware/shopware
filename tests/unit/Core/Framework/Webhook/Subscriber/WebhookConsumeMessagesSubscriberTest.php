<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Webhook\Subscriber\WebhookConsumeMessagesSubscriber;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @internal
 */
#[CoversClass(WebhookConsumeMessagesSubscriber::class)]
class WebhookConsumeMessagesSubscriberTest extends TestCase
{
    public function testInjectsWebhookBeforeAsyncAndPreservesOtherReceivers(): void
    {
        $event = $this->makeConsumeEvent(['async', 'low_priority']);

        Feature::withFeatureDisabled('v6.8.0.0', function () use ($event): void {
            Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
                (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
            });
        });

        static::assertSame(['webhook', 'async', 'low_priority'], $event->getInput()->getArgument('receivers'));
    }

    /**
     * @param list<string> $receivers
     */
    #[DataProvider('noOpCases')]
    public function testNoOpLeavesReceiversUnchanged(array $receivers, string $commandName, bool $v680Active, bool $reworkActive): void
    {
        $event = $this->makeConsumeEvent($receivers, commandName: $commandName);

        $invoke = static function () use ($event, $reworkActive): void {
            $run = static function () use ($event): void {
                (new WebhookConsumeMessagesSubscriber())->onMessengerConsume($event);
            };

            if ($reworkActive) {
                Feature::withFeatureEnabled('WEBHOOKS_REWORK', $run);
            } else {
                Feature::withFeatureDisabled('WEBHOOKS_REWORK', $run);
            }
        };

        if ($v680Active) {
            Feature::withFeatureEnabled('v6.8.0.0', $invoke);
        } else {
            Feature::withFeatureDisabled('v6.8.0.0', $invoke);
        }

        static::assertSame($receivers, $event->getInput()->getArgument('receivers'));
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: string, 2: bool, 3: bool}>
     */
    public static function noOpCases(): iterable
    {
        yield 'webhook already present' => [['webhook', 'async'], 'messenger:consume', false, true];
        yield 'async absent' => [['low_priority'], 'messenger:consume', false, true];
        yield 'only failed receiver' => [['failed'], 'messenger:consume', false, true];
        yield 'receivers empty' => [[], 'messenger:consume', false, true];
        yield 'unrelated command' => [['async'], 'cache:clear', false, true];
        yield 'v6.8.0.0 active' => [['async'], 'messenger:consume', true, true];
        yield 'rework flag off' => [['async'], 'messenger:consume', false, false];
    }

    /**
     * @param list<string> $receivers
     */
    private function makeConsumeEvent(
        array $receivers,
        string $commandName = 'messenger:consume',
    ): ConsoleCommandEvent {
        $command = new Command($commandName);
        $command->setDefinition(new InputDefinition([
            new InputArgument('receivers', InputArgument::IS_ARRAY),
        ]));

        return new ConsoleCommandEvent(
            $command,
            new ArrayInput(['receivers' => $receivers], $command->getDefinition()),
            new NullOutput(),
        );
    }
}

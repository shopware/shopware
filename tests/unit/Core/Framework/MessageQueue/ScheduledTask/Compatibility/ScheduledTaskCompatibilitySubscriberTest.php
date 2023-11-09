<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Compatibility;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Compatibility\ScheduledTaskCompatibilitySubscriber;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\MessageQueue\ScheduledTask\Compatibility\ScheduledTaskCompatibilitySubscriber
 *
 * @deprecated tag:v6.6.0 - can safely be deleted when the subscriber is removed
 */
#[Package('core')]
class ScheduledTaskCompatibilitySubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertEquals(
            [PreWriteValidationEvent::class => 'addBackwardsCompatibility'],
            ScheduledTaskCompatibilitySubscriber::getSubscribedEvents(),
        );
    }

    /**
     * @DisabledFeatures("v6.6.0.0")
     *
     * @dataProvider addBackwardsCompatibilityProvider
     */
    public function testAddBackwardsCompatibility(WriteCommand $inputCommand, WriteCommand $expectedCommand): void
    {
        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [$inputCommand]);

        $subscriber = new ScheduledTaskCompatibilitySubscriber();
        $subscriber->addBackwardsCompatibility($event);

        static::assertEquals([$expectedCommand], $event->getCommands());
    }

    public function testSubscriberHasNoEffectWhenFeatureIsEnabled(): void
    {
        $dummyExistence = new EntityExistence('', [], true, true, true, []);
        $insertCommand = new InsertCommand(new ScheduledTaskDefinition(), [
            'id' => 'id',
            'name' => 'name',
            'run_interval' => 1,
        ], [], $dummyExistence, '');
        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [$insertCommand]);

        $subscriber = new ScheduledTaskCompatibilitySubscriber();
        $subscriber->addBackwardsCompatibility($event);

        static::assertEquals([$insertCommand], $event->getCommands());
    }

    public static function addBackwardsCompatibilityProvider(): \Generator
    {
        $dummyExistence = new EntityExistence('', [], true, true, true, []);

        $updateCommand = new UpdateCommand(new ScheduledTaskDefinition(), [
            'id' => 'id',
            'name' => 'name',
            'run_interval' => 1,
        ], [], $dummyExistence, '');

        yield 'skip if not insert command' => [
            $updateCommand,
            $updateCommand,
        ];

        $insertCommand = new InsertCommand(new ProductDefinition(), [
            'id' => 'id',
            'name' => 'name',
            'run_interval' => 1,
        ], [], $dummyExistence, '');

        yield 'skip if not for scheduled task entity' => [
            $insertCommand,
            $insertCommand,
        ];

        $insertCommand = new InsertCommand(new ScheduledTaskDefinition(), [
            'id' => 'id',
            'name' => 'name',
            'run_interval' => 1,
            'default_run_interval' => 2,
        ], [], $dummyExistence, '');

        yield 'skip if default run interval is provided' => [
            $insertCommand,
            $insertCommand,
        ];

        $insertCommand = new InsertCommand(new ScheduledTaskDefinition(), [
            'id' => 'id',
            'name' => 'name',
            'run_interval' => 1,
        ], [], $dummyExistence, '');
        $expectedCommand = clone $insertCommand;
        $expectedCommand->addPayload('default_run_interval', 1);

        yield 'adds default run interval base on runInterval' => [
            $insertCommand,
            $expectedCommand,
        ];
    }
}

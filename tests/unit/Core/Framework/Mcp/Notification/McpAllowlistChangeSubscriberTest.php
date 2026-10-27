<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpAllowlistChangeSubscriber;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpAllowlistChangeSubscriber::class)]
class McpAllowlistChangeSubscriberTest extends TestCase
{
    private const FIELD = 'mcp_allowlist';

    private const BEFORE = '{"tools":["shopware-order-search"],"resources":null,"prompts":null}';

    public function testSubscribesToTheEntityWriteEvent(): void
    {
        static::assertSame(
            [EntityWriteEvent::class => 'onWrite'],
            McpAllowlistChangeSubscriber::getSubscribedEvents(),
        );
    }

    /**
     * @return iterable<string, array{class-string<EntityDefinition>}>
     */
    public static function principalProvider(): iterable
    {
        yield 'integration' => [IntegrationDefinition::class];
        yield 'user' => [UserDefinition::class];
    }

    /**
     * @param class-string<EntityDefinition> $definition
     */
    #[DataProvider('principalProvider')]
    public function testAnEditedAllowlistRaisesTheListsWhoseEntryChanged(string $definition): void
    {
        $command = $this->update($definition, self::BEFORE, '{"tools":["shopware-product-search"],"resources":null,"prompts":["shopware-context"]}');
        $notifier = $this->expectNotification(tools: true, resources: false, prompts: true);

        $event = $this->write($command);
        (new McpAllowlistChangeSubscriber($notifier))->onWrite($event);

        static::assertTrue($command->requiresChangeSet(), 'the subscriber asks the gateway for the previous value');

        $event->success();
    }

    public function testRemovingTheAllowlistRaisesTheRestrictedLists(): void
    {
        $command = $this->update(IntegrationDefinition::class, self::BEFORE, null);
        $notifier = $this->expectNotification(tools: true, resources: false, prompts: false);

        $event = $this->write($command);
        (new McpAllowlistChangeSubscriber($notifier))->onWrite($event);
        $event->success();
    }

    public function testNothingIsAnnouncedBeforeTheWriteSucceeded(): void
    {
        $command = $this->update(IntegrationDefinition::class, self::BEFORE, null);
        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notify');

        $event = $this->write($command);
        (new McpAllowlistChangeSubscriber($notifier))->onWrite($event);
        $event->error();
    }

    public function testTheSameEntriesInAnotherOrderAreNoChange(): void
    {
        $command = $this->update(IntegrationDefinition::class, self::BEFORE, '{"prompts":null,"resources":null,"tools":["shopware-order-search"]}');
        $notifier = $this->expectNotification(tools: false, resources: false, prompts: false);

        $event = $this->write($command);
        (new McpAllowlistChangeSubscriber($notifier))->onWrite($event);
        $event->success();
    }

    /**
     * The change set drops a column whose value did not change, so its `after` is null just like
     * for a removed allowlist. An identical write must not read as a removal.
     */
    public function testAnIdenticalWriteIsNoChange(): void
    {
        $command = $this->update(IntegrationDefinition::class, self::BEFORE, self::BEFORE);
        $notifier = $this->expectNotification(tools: false, resources: false, prompts: false);

        $event = $this->write($command);
        (new McpAllowlistChangeSubscriber($notifier))->onWrite($event);
        $event->success();
    }

    public function testChangesOfSeveralPrincipalsAreMerged(): void
    {
        $integration = $this->update(IntegrationDefinition::class, self::BEFORE, '{"tools":["shopware-product-search"],"resources":null,"prompts":null}');
        $user = $this->update(UserDefinition::class, self::BEFORE, '{"tools":["shopware-order-search"],"resources":["shopware://currencies"],"prompts":null}');
        $notifier = $this->expectNotification(tools: true, resources: true, prompts: false);

        $event = $this->write($integration, $user);
        (new McpAllowlistChangeSubscriber($notifier))->onWrite($event);
        $event->success();
    }

    public function testAnUnreadableStoredValueCountsAsUnrestricted(): void
    {
        $command = $this->update(IntegrationDefinition::class, 'not json', '{"tools":["shopware-order-search"],"resources":null,"prompts":null}');
        $notifier = $this->expectNotification(tools: true, resources: false, prompts: false);

        $event = $this->write($command);
        (new McpAllowlistChangeSubscriber($notifier))->onWrite($event);
        $event->success();
    }

    /**
     * @return iterable<string, array{WriteCommand}>
     */
    public static function unrelatedWriteProvider(): iterable
    {
        yield 'update without the allowlist' => [self::command(UpdateCommand::class, IntegrationDefinition::class, ['label' => 'renamed'])];
        yield 'insert with an allowlist' => [self::command(InsertCommand::class, IntegrationDefinition::class, [self::FIELD => self::BEFORE])];
        yield 'update of another entity' => [self::command(UpdateCommand::class, SalesChannelDefinition::class, [self::FIELD => self::BEFORE])];
    }

    #[DataProvider('unrelatedWriteProvider')]
    public function testUnrelatedWritesRaiseNothing(WriteCommand $command): void
    {
        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notify');

        $event = $this->write($command);
        (new McpAllowlistChangeSubscriber($notifier))->onWrite($event);

        if ($command instanceof UpdateCommand) {
            static::assertFalse($command->requiresChangeSet());
        }

        $event->success();
    }

    private function expectNotification(bool $tools, bool $resources, bool $prompts): McpListChangedNotifier
    {
        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with(static::callback(static function (McpListChangedNotificationSet $set) use ($tools, $resources, $prompts): bool {
                static::assertSame(['tools' => $tools, 'resources' => $resources, 'prompts' => $prompts], ['tools' => $set->tools, 'resources' => $set->resources, 'prompts' => $set->prompts]);

                return true;
            }));

        return $notifier;
    }

    /**
     * Builds the update the gateway would produce: the command carries the new value, the change set the
     * stored one, and a value that did not change is missing from the change set's `after`.
     *
     * @param class-string<EntityDefinition> $definition
     */
    private function update(string $definition, string $before, ?string $after): UpdateCommand
    {
        $command = self::command(UpdateCommand::class, $definition, [self::FIELD => $after]);
        $command->setChangeSet(new ChangeSet([self::FIELD => $before], [self::FIELD => $after], false));

        return $command;
    }

    /**
     * @template T of WriteCommand
     *
     * @param class-string<T> $class
     * @param class-string<EntityDefinition> $definition
     * @param array<string, mixed> $payload
     *
     * @return T
     */
    private static function command(string $class, string $definition, array $payload): WriteCommand
    {
        new StaticDefinitionInstanceRegistry(
            [$instance = new $definition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $id = Uuid::randomHex();

        return new $class(
            $instance,
            $payload,
            ['id' => Uuid::fromHexToBytes($id)],
            new EntityExistence($instance->getEntityName(), ['id' => $id], $class === UpdateCommand::class, false, false, []),
            '/0',
        );
    }

    private function write(WriteCommand ...$commands): EntityWriteEvent
    {
        return EntityWriteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
    }
}

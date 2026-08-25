<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlTemplate;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateChangeSubscriber;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlTemplateChangeSubscriber::class)]
class SeoUrlTemplateChangeSubscriberTest extends TestCase
{
    private SeoUrlTemplateDefinition $definition;

    protected function setUp(): void
    {
        // Compiles the definition: WriteCommand constructors need getPrimaryKeys()
        // which only works after registration in a DefinitionInstanceRegistry.
        new StaticDefinitionInstanceRegistry(
            [$this->definition = new SeoUrlTemplateDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [EntityWriteEvent::class => 'onSeoUrlTemplateWrite'],
            SeoUrlTemplateChangeSubscriber::getSubscribedEvents()
        );
    }

    public function testDispatchesIndexingMessageForInsertedTemplate(): void
    {
        // Inserts carry route and entity name in the payload, no lookup needed.
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn (SeoUrlTemplateIndexingMessage $message): bool => $message->routeName === 'frontend.navigation.page'
                && $message->entityName === 'category'))
            ->willReturn(new Envelope(new \stdClass()));

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);

        $event = $this->createEvent([
            $this->insertCommand([
                'template' => 'custom-prefix/{{ category.name }}',
                'route_name' => 'frontend.navigation.page',
                'entity_name' => 'category',
            ]),
        ]);

        $subscriber->onSeoUrlTemplateWrite($event);
        $event->success();
    }

    public function testDispatchesIndexingMessageWhenTemplateChanged(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => 'frontend.navigation.page', 'entityName' => 'category'],
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn (SeoUrlTemplateIndexingMessage $message): bool => $message->routeName === 'frontend.navigation.page'
                && $message->entityName === 'category'))
            ->willReturn(new Envelope(new \stdClass()));

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);

        $command = $this->updateCommand(['template' => 'custom-prefix/{{ category.name }}']);
        $event = $this->createEvent([$command]);

        $subscriber->onSeoUrlTemplateWrite($event);

        static::assertTrue($command->requiresChangeSet());
        $command->setChangeSet(new ChangeSet(
            ['template' => 'old/{{ category.name }}'],
            ['template' => 'custom-prefix/{{ category.name }}'],
            false
        ));

        $event->success();
    }

    public function testIgnoresUpdateResubmittingTheSameTemplate(): void
    {
        // An idempotent API update that submits the identical template again must
        // not trigger the expensive reindexing pass.
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);

        $command = $this->updateCommand(['template' => 'same/{{ category.name }}']);
        $event = $this->createEvent([$command]);

        $subscriber->onSeoUrlTemplateWrite($event);

        static::assertTrue($command->requiresChangeSet());
        $command->setChangeSet(new ChangeSet(
            ['template' => 'same/{{ category.name }}'],
            ['template' => 'same/{{ category.name }}'],
            false
        ));

        $event->success();
    }

    public function testStillDispatchesWhenChangeSetIsMissing(): void
    {
        // Defensive: without a change set the previous value is unknown, so the
        // subscriber must regenerate rather than silently skip.
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => 'frontend.navigation.page', 'entityName' => 'category'],
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);

        $event = $this->createEvent([$this->updateCommand(['template' => 'x'])]);

        $subscriber->onSeoUrlTemplateWrite($event);
        $event->success();
    }

    public function testIgnoresWritesWithoutTemplatePayload(): void
    {
        // Partial writes such as custom-fields-only saves must not trigger the
        // expensive reindexing pass.
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);

        $command = $this->updateCommand(['custom_fields' => '{"foo": "bar"}']);
        $event = $this->createEvent([$command]);

        $subscriber->onSeoUrlTemplateWrite($event);

        static::assertFalse($command->requiresChangeSet());
        $event->success();
    }

    public function testIgnoresInsertWithoutTemplateValue(): void
    {
        // The admin creates placeholder rows without a template for every route of a
        // sales channel; those fall back to the default template, so there is nothing
        // to regenerate.
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);

        $event = $this->createEvent([
            $this->insertCommand([
                'template' => null,
                'route_name' => 'frontend.navigation.page',
                'entity_name' => 'category',
            ]),
            $this->insertCommand([
                'template' => '',
                'route_name' => 'frontend.detail.page',
                'entity_name' => 'product',
            ]),
        ]);

        $subscriber->onSeoUrlTemplateWrite($event);
        $event->success();
    }

    public function testIgnoresWritesOfOtherEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);

        $event = $this->createEvent([]);

        $subscriber->onSeoUrlTemplateWrite($event);
        $event->success();
    }

    public function testDispatchesOneMessagePerRoute(): void
    {
        // Multiple writes resolving to the same route must produce a single message.
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => 'frontend.navigation.page', 'entityName' => 'category'],
            ['routeName' => 'frontend.detail.page', 'entityName' => 'product'],
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);

        $event = $this->createEvent([
            $this->insertCommand([
                'template' => 'a',
                'route_name' => 'frontend.navigation.page',
                'entity_name' => 'category',
            ]),
            $this->updateCommand(['template' => 'b']),
            $this->updateCommand(['template' => 'c']),
        ]);

        $subscriber->onSeoUrlTemplateWrite($event);
        $event->success();
    }

    public function testIgnoresRoutesWithEmptyRouteOrEntityName(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => '', 'entityName' => 'category'],
            ['routeName' => 'frontend.navigation.page', 'entityName' => ''],
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);

        $event = $this->createEvent([$this->updateCommand(['template' => 'x'])]);

        $subscriber->onSeoUrlTemplateWrite($event);
        $event->success();
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function createEvent(array $commands): EntityWriteEvent
    {
        return EntityWriteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertCommand(array $payload): InsertCommand
    {
        $id = Uuid::randomBytes();

        return new InsertCommand(
            $this->definition,
            [...$payload, 'id' => $id],
            ['id' => $id],
            $this->existence(false),
            '/0'
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateCommand(array $payload): UpdateCommand
    {
        return new UpdateCommand(
            $this->definition,
            $payload,
            ['id' => Uuid::randomBytes()],
            $this->existence(true),
            '/0'
        );
    }

    private function existence(bool $exists): EntityExistence
    {
        return new EntityExistence(SeoUrlTemplateDefinition::ENTITY_NAME, [], $exists, false, false, []);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\DocumentV2\Subscriber\DocumentBaseConfigSyncSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentBaseConfigSyncSubscriber::class)]
class DocumentBaseConfigSyncSubscriberTest extends TestCase
{
    private DocumentBaseConfigDefinition $definition;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $registry = new StaticDefinitionInstanceRegistry(
            [$this->definition = new DocumentBaseConfigDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
        );

        $this->definition->compile($registry);
    }

    public function testSkipDeleteCommands(): void
    {
        $command = $this->createMock(DeleteCommand::class);
        $command->expects($this->never())->method('getPayload');
        $command->expects($this->once())->method('getEntityName')
            ->willReturn(DocumentBaseConfigDefinition::ENTITY_NAME);

        [$subscriber, $event] = $this->createSubscriber([$command]);

        $subscriber->syncDocumentBaseConfig($event);
    }

    public function testIgnoresEventWithoutDocumentBaseConfigCommands(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        [$subscriber, $event] = $this->createSubscriber([], $connection);

        $subscriber->syncDocumentBaseConfig($event);
    }

    public function testSkipsCommandWithNeitherColumnNorConfigPayload(): void
    {
        $command = $this->createInsertCommand(['name' => 'invoice']);

        [$subscriber, $event] = $this->createSubscriber([$command]);
        $subscriber->syncDocumentBaseConfig($event);

        static::assertSame(['name' => 'invoice'], $command->getPayload());
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $expected
     * @param list<string> $absentKeys
     */
    #[DataProvider('provideSyncCases')]
    public function testSync(
        string $commandClass,
        array $payload,
        ?string $existingJson,
        array $expected,
        array $absentKeys = [],
    ): void {
        $command = $commandClass === InsertCommand::class
            ? $this->createInsertCommand($payload)
            : $this->createUpdateCommand($payload);

        $connection = $this->createMock(Connection::class);

        if ($existingJson !== null) {
            $connection->expects($this->once())->method('fetchOne')->willReturn($existingJson);
        } else {
            $connection->expects($this->never())->method('fetchOne');
        }

        [$subscriber, $event] = $this->createSubscriber([$command], $connection);
        $subscriber->syncDocumentBaseConfig($event);

        $actual = $command->getPayload();

        foreach ($expected as $key => $value) {
            static::assertArrayHasKey($key, $actual, $key);

            if ($key === 'config' && \is_string($value)) {
                $expectedJson = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
                $actualJson = json_decode($actual[$key], true, 512, \JSON_THROW_ON_ERROR);

                static::assertIsArray($expectedJson);
                static::assertIsArray($actualJson);

                ksort($expectedJson);
                ksort($actualJson);

                static::assertSame($expectedJson, $actualJson);

                continue;
            }

            static::assertSame($value, $actual[$key], $key);
        }

        foreach ($absentKeys as $key) {
            static::assertArrayNotHasKey($key, $actual, $key);
        }
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function provideSyncCases(): iterable
    {
        yield 'insert: column-only payload writes JSON config' => [
            'commandClass' => InsertCommand::class,
            'payload' => ['page_size' => 'A4', 'display_header' => 1, 'items_per_page' => 25],
            'existingJson' => null,
            'expected' => [
                'page_size' => 'A4',
                'display_header' => 1,
                'items_per_page' => 25,
                'config' => '{"pageSize":"A4","displayHeader":true,"itemsPerPage":25}',
            ],
        ];

        yield 'insert: bool column 0 maps to JSON false' => [
            'commandClass' => InsertCommand::class,
            'payload' => ['display_header' => 0],
            'existingJson' => null,
            'expected' => [
                'display_header' => 0,
                'config' => '{"displayHeader":false}',
            ],
        ];

        yield 'insert: NULL column propagates as JSON null' => [
            'commandClass' => InsertCommand::class,
            'payload' => ['page_size' => null],
            'existingJson' => null,
            'expected' => [
                'page_size' => null,
                'config' => '{"pageSize":null}',
            ],
        ];

        yield 'insert: int column from string is cast in JSON' => [
            'commandClass' => InsertCommand::class,
            'payload' => ['items_per_page' => '25'],
            'existingJson' => null,
            'expected' => [
                'items_per_page' => '25',
                'config' => '{"itemsPerPage":25}',
            ],
        ];

        yield 'insert: config payload promotes mapped keys to columns' => [
            'commandClass' => InsertCommand::class,
            'payload' => ['config' => '{"pageSize":"A4","displayHeader":true,"itemsPerPage":25}'],
            'existingJson' => null,
            'expected' => [
                'page_size' => 'A4',
                'display_header' => 1,
                'items_per_page' => 25,
                'config' => '{"pageSize":"A4","displayHeader":true,"itemsPerPage":25}',
            ],
        ];

        yield 'insert: unmapped JSON keys are preserved without column promotion' => [
            'commandClass' => InsertCommand::class,
            'payload' => ['config' => '{"companyName":"shopware"}'],
            'existingJson' => null,
            'expected' => [
                'config' => '{"companyName":"shopware"}',
            ],
            'absentKeys' => ['page_size', 'display_header', 'items_per_page'],
        ];

        yield 'insert: column wins when both column and config carry the same key' => [
            'commandClass' => InsertCommand::class,
            'payload' => [
                'page_size' => 'A4',
                'config' => '{"pageSize":"Letter"}',
            ],
            'existingJson' => null,
            'expected' => [
                'page_size' => 'A4',
                'config' => '{"pageSize":"A4"}',
            ],
        ];

        yield 'update: column payload merges into existing JSON without clobbering siblings' => [
            'commandClass' => UpdateCommand::class,
            'payload' => ['page_size' => 'Letter'],
            'existingJson' => '{"pageSize":"A4","companyName":"shopware"}',
            'expected' => [
                'page_size' => 'Letter',
                'config' => '{"pageSize":"Letter","companyName":"shopware"}',
            ],
        ];

        yield 'update: column already matches existing JSON skips redundant config payload' => [
            'commandClass' => UpdateCommand::class,
            'payload' => ['page_size' => 'A4'],
            'existingJson' => '{"pageSize":"A4"}',
            'expected' => ['page_size' => 'A4'],
            'absentKeys' => ['config'],
        ];
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return array{0: DocumentBaseConfigSyncSubscriber, 1: EntityWriteEvent}
     */
    private function createSubscriber(array $commands = [], ?Connection $connection = null): array
    {
        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());
        $event = EntityWriteEvent::create($writeContext, $commands);

        $subscriber = new DocumentBaseConfigSyncSubscriber(
            $connection ?? $this->createMock(Connection::class)
        );

        return [$subscriber, $event];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createInsertCommand(array $payload): InsertCommand
    {
        return new InsertCommand(
            $this->definition,
            $payload,
            ['id' => $this->ids->getBytes('config')],
            $this->createMock(EntityExistence::class),
            '/0',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createUpdateCommand(array $payload): UpdateCommand
    {
        return new UpdateCommand(
            $this->definition,
            $payload,
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0',
        );
    }
}

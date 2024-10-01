<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\CustomFieldEvents;
use Shopware\Core\System\CustomField\CustomFieldException;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomFieldService::class)]
class CustomFieldServiceTest extends TestCase
{
    private const VALID_NAME = 'valid_name';

    private const INVALID_NAME = 'invalid-name';

    private MockObject&Connection $connection;

    private CustomFieldService $customFieldService;

    private WriteContext $writeContext;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->customFieldService = new CustomFieldService($this->connection);
        $this->writeContext = WriteContext::createFromContext(
            Context::createDefaultContext()
        );
    }

    /**
     * @param class-string<object>|null $expected
     */
    #[DataProvider('getCustomFieldValues')]
    public function testGetCustomField(?string $type, ?string $expected): void
    {
        $attributeName = 'test';
        $this->connection->method('fetchAllKeyValue')->willReturn([
            $attributeName => $type,
        ]);

        $result = $this->customFieldService->getCustomField($attributeName);

        if (!$expected) {
            static::assertNull($result);

            return;
        }

        static::assertInstanceOf($expected, $result);
        static::assertInstanceOf(ApiAware::class, $result->getFlags()[0]);
    }

    /**
     * @return iterable<string, array{type: string|null, expected: class-string<Field>|null}>
     */
    public static function getCustomFieldValues(): iterable
    {
        yield 'null' => ['type' => null, 'expected' => null];
        yield 'int' => ['type' => CustomFieldTypes::INT, 'expected' => IntField::class];
        yield 'float' => ['type' => CustomFieldTypes::FLOAT, 'expected' => FloatField::class];
        yield 'bool' => ['type' => CustomFieldTypes::BOOL, 'expected' => BoolField::class];
        yield 'datetime' => ['type' => CustomFieldTypes::DATETIME, 'expected' => DateTimeField::class];
        yield 'text' => ['type' => CustomFieldTypes::TEXT, 'expected' => LongTextField::class];
        yield 'html' => ['type' => CustomFieldTypes::HTML, 'expected' => LongTextField::class];
        yield 'price' => ['type' => CustomFieldTypes::PRICE, 'expected' => PriceField::class];
        yield 'unknown' => ['type' => 'unknown', 'expected' => JsonField::class];
    }

    #[DataProvider('getCustomFieldNames')]
    public function testValidateCustomFieldInvalidName(string $name, bool $error): void
    {
        $command = $this->createCommand($name);

        $event = EntityWriteEvent::create(
            $this->writeContext,
            [$command],
        );

        if ($error) {
            static::expectExceptionObject(
                CustomFieldException::customFieldNameInvalid($name)
            );
        } else {
            static::expectNotToPerformAssertions();
        }

        $this->customFieldService->validateBeforeWrite($event);
    }

    /**
     * @return iterable<string, array{name: string, error: bool}>
     */
    public static function getCustomFieldNames(): iterable
    {
        yield 'valid' => ['name' => self::VALID_NAME, 'error' => false];
        yield 'valid: start with underscore' => ['name' => '_valid_name', 'error' => false];
        yield 'valid: contains digits' => ['name' => 'valid_name_123', 'error' => false];
        yield 'valid: contains ASCII chars' => ['name' => 'valid_name_äöü', 'error' => false];
        yield 'invalid: start with digits' => ['name' => '123_invalid_name', 'error' => true];
        yield 'invalid: start with special chars' => ['name' => '@_invalid_name', 'error' => true];
        yield 'invalid: contains spaces' => ['name' => 'invalid name', 'error' => true];
        yield 'invalid: contains hyphens' => ['name' => 'invalid-name', 'error' => true];
        yield 'invalid: contains new line' => ['name' => 'invalid\nName', 'error' => true];
    }

    public function testGetCustomFieldNameNotExisting(): void
    {
        $this->connection->method('fetchAllKeyValue')->willReturn([]);

        $result = $this->customFieldService->getCustomField('test');
        static::assertNull($result);
    }

    public function testGetCustomFieldShouldNotRefetch(): void
    {
        $this->connection->expects(static::once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                ['test' => CustomFieldTypes::TEXT],
            ]);

        $this->customFieldService->getCustomField('test');
        $this->customFieldService->getCustomField('test');
    }

    public function testReset(): void
    {
        $this->connection->expects(static::exactly(2))
            ->method('fetchAllKeyValue')
            ->willReturnOnConsecutiveCalls(
                [],
                ['test' => CustomFieldTypes::TEXT],
            );

        static::assertNull($this->customFieldService->getCustomField('test'));
        $this->customFieldService->reset();
        static::assertNotNull($this->customFieldService->getCustomField('test'));
    }

    public function testSubscribedEvents(): void
    {
        $events = CustomFieldService::getSubscribedEvents();

        static::assertSame(
            [
                CustomFieldEvents::CUSTOM_FIELD_DELETED_EVENT => 'reset',
                CustomFieldEvents::CUSTOM_FIELD_WRITTEN_EVENT => 'reset',
                EntityWriteEvent::class => 'validateBeforeWrite',
            ],
            $events
        );
    }

    public function testValidateWithoutNameShouldSkipValidation(): void
    {
        $command = $this->createCommand(null);

        $event = EntityWriteEvent::create(
            $this->writeContext,
            [$command],
        );

        static::expectNotToPerformAssertions();

        $this->customFieldService->validateBeforeWrite($event);
    }

    public function testValidateWithDifferentEntityShouldSkipValidation(): void
    {
        $command = $this->createCommand(
            self::INVALID_NAME,
            CustomerDefinition::ENTITY_NAME,
            [CustomerDefinition::class]
        );

        $event = EntityWriteEvent::create(
            $this->writeContext,
            [$command],
        );

        static::expectNotToPerformAssertions();

        $this->customFieldService->validateBeforeWrite($event);
    }

    public function testValidateWithEmptyCommandsShouldSkipValidation(): void
    {
        $event = EntityWriteEvent::create(
            $this->writeContext,
            [],
        );

        static::expectNotToPerformAssertions();

        $this->customFieldService->validateBeforeWrite($event);
    }

    public function testValidNameShouldPassValidation(): void
    {
        $command = $this->createCommand(self::VALID_NAME);

        $event = EntityWriteEvent::create(
            $this->writeContext,
            [$command],
        );

        static::expectNotToPerformAssertions();

        $this->customFieldService->validateBeforeWrite($event);
    }

    /**
     * @param array<int, class-string<EntityDefinition>> $registryDefinitions
     */
    private function createCommand(
        ?string $name,
        string $commandEntity = CustomFieldSetDefinition::ENTITY_NAME,
        array $registryDefinitions = [CustomFieldSetDefinition::class]
    ): InsertCommand {
        $registry = new StaticDefinitionInstanceRegistry(
            $registryDefinitions,
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
        );

        $payload = $name ? ['name' => $name] : [];

        $entityExistence = new EntityExistence(
            $commandEntity,
            $payload,
            true,
            false,
            false,
            []
        );

        return new InsertCommand(
            $registry->getByEntityName($commandEntity),
            $payload,
            ['id' => Uuid::randomBytes()],
            $entityExistence,
            '/0'
        );
    }
}

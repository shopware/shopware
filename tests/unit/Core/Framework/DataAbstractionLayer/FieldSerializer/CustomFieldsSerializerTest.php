<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomFieldsSerializer::class)]
class CustomFieldsSerializerTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    private CustomFieldsSerializerTestDefinition $definition;

    private CustomFieldsSerializer $serializer;

    private WriteCommandQueue $queue;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            ['CustomFieldsSerializerTest' => $this->definition = new CustomFieldsSerializerTestDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $attributeService = static::createStub(CustomFieldService::class);
        $attributeService->method('getCustomField')->willReturn(new StringField('custom_text', 'customText'));

        $this->serializer = new CustomFieldsSerializer(
            $this->registry,
            static::createStub(ValidatorInterface::class),
            $attributeService
        );

        $this->queue = new WriteCommandQueue();
    }

    public function testEncodeQueuesAJsonUpdateForAnAlreadyExistingEntity(): void
    {
        $id = Uuid::randomHex();

        $encoded = $this->serializer->encode(
            $this->customFieldsField(),
            $this->existence($id, 'custom_fields_serializer_test'),
            new KeyValuePair('customFields', ['customText' => 'foo'], true),
            $this->parameters()
        );

        static::assertSame([], iterator_to_array($encoded), 'An existing entity is patched via a JSON update, not by writing the whole column');

        $commands = $this->queue->getCommandsInOrder($this->registry);
        static::assertCount(1, $commands);

        $command = $commands[0];
        static::assertInstanceOf(JsonUpdateCommand::class, $command);
        static::assertSame('custom_fields', $command->getStorageName());
        static::assertSame('custom_fields_serializer_test', $command->getEntityName());
        static::assertSame(['custom_text' => 'foo'], $command->getPayload(), 'The payload is keyed by the custom field storage name');
        static::assertSame(['id' => Uuid::fromHexToBytes($id)], $command->getPrimaryKey());
    }

    public function testEncodeQueuesNothingWhenTheExistenceHasNoEntityName(): void
    {
        $encoded = $this->serializer->encode(
            $this->customFieldsField(),
            $this->existence(Uuid::randomHex(), null),
            new KeyValuePair('customFields', ['customText' => 'foo'], true),
            $this->parameters()
        );

        static::assertSame([], iterator_to_array($encoded));
        static::assertSame([], $this->queue->getCommandsInOrder($this->registry));
    }

    private function customFieldsField(): CustomFields
    {
        $field = $this->definition->getField('customFields');
        static::assertInstanceOf(CustomFields::class, $field);

        return $field;
    }

    private function existence(string $id, ?string $entityName): EntityExistence
    {
        return new EntityExistence($entityName, ['id' => $id], true, false, false, []);
    }

    private function parameters(): WriteParameterBag
    {
        return new WriteParameterBag(
            $this->definition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            $this->queue
        );
    }
}

/**
 * @internal
 */
class CustomFieldsSerializerTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'custom_fields_serializer_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new CustomFields('custom_fields', 'customFields'),
        ]);
    }
}

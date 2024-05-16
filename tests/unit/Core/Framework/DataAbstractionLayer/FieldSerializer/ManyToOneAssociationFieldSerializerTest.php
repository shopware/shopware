<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ManyToOneAssociationFieldSerializer::class)]
class ManyToOneAssociationFieldSerializerTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $payload
     */
    #[DataProvider('invalidArrayProvider')]
    public function testExceptionIsThrownIfDataIsNotAssociativeArray(array $payload): void
    {
        $this->expectException(DataAbstractionLayerException::class);
        static::expectExceptionMessage('Expected data at /customer to be an associative array.');

        new StaticDefinitionInstanceRegistry(
            [
                OrderDefinition::class => $orderDefinition = new OrderDefinition(),
                CustomerDefinition::class => new CustomerDefinition(),
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $field = $orderDefinition->getField('customer');

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);

        $serializer = new ManyToOneAssociationFieldSerializer($this->createMock(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $orderDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/customer',
            new WriteCommandQueue()
        );

        $result = $serializer->encode(
            $field,
            $this->createMock(EntityExistence::class),
            new KeyValuePair('customer', $payload, true),
            $params
        );

        iterator_to_array($result);
    }

    public static function invalidArrayProvider(): \Generator
    {
        yield [
            'payload' => ['should-be-an-associative-array'],
        ];

        yield [
            'payload' => [1 => 'apple', 'orange'],
        ];

        yield [
            'payload' => [0 => 'apple', 1 => 'orange'],
        ];

        yield [
            'payload' => [3 => 'apple', 5 => 'orange'],
        ];
    }

    public function testCanEncodeAssociativeArray(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                OrderDefinition::class => $orderDefinition = new OrderDefinition(),
                CustomerDefinition::class => new CustomerDefinition(),
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $field = $orderDefinition->getField('customer');

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);

        $serializer = new ManyToOneAssociationFieldSerializer($this->createMock(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $orderDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/customer',
            new WriteCommandQueue()
        );

        $id = Uuid::randomHex();

        $result = $serializer->encode(
            $field,
            $this->createMock(EntityExistence::class),
            new KeyValuePair('customer', ['id' => $id, 'name' => 'Jimmy'], true),
            $params
        );

        static::assertEquals([], iterator_to_array($result));
    }
}

/**
 * @internal
 */
class OrderDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'order';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new FkField('customer_id', 'customerId', CustomerDefinition::class),

            new ManyToOneAssociationField(
                'customer',
                'customer_id',
                CustomerDefinition::class,
                'id',
            ),
        ]);
    }
}

/**
 * @internal
 */
class CustomerDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'customer';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('first_name', 'first_name'))->addFlags(new Required()),
            (new StringField('last_name', 'last_name'))->addFlags(new Required()),

            new OneToManyAssociationField(
                'orders',
                OrderDefinition::class,
                'customer_id',
            ),
        ]);
    }
}

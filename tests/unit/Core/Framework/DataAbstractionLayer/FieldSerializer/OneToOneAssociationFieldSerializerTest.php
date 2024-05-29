<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ManyToOneAssociationFieldSerializer::class)]
class OneToOneAssociationFieldSerializerTest extends TestCase
{
    public function testExceptionInNormalizationIsThrownIfDataIsNotArray(): void
    {
        $this->expectException(ExpectedArrayException::class);
        static::expectExceptionMessage('Expected data at /0/recoveryCustomer to be an array.');

        new StaticDefinitionInstanceRegistry(
            [
                TestCustomerDefinition::class => $customerDefinition = new TestCustomerDefinition(),
                CustomerRecoveryDefinition::class => new CustomerRecoveryDefinition(),
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $field = $customerDefinition->getField('recoveryCustomer');

        static::assertInstanceOf(OneToOneAssociationField::class, $field);

        $serializer = new OneToOneAssociationFieldSerializer($this->createMock(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $customerDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );

        $serializer->normalize(
            $field,
            ['recoveryCustomer' => 'foobar'],
            $params,
        );
    }

    public function testExceptionInEncodeIsThrownIfDataIsNotArray(): void
    {
        $this->expectException(ExpectedArrayException::class);
        static::expectExceptionMessage('Expected data at /0/recoveryCustomer to be an array.');

        new StaticDefinitionInstanceRegistry(
            [
                TestCustomerDefinition::class => $customerDefinition = new TestCustomerDefinition(),
                CustomerRecoveryDefinition::class => new CustomerRecoveryDefinition(),
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $field = $customerDefinition->getField('recoveryCustomer');

        static::assertInstanceOf(OneToOneAssociationField::class, $field);

        $serializer = new OneToOneAssociationFieldSerializer($this->createMock(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $customerDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );

        $serializer->encode(
            $field,
            $this->createMock(EntityExistence::class),
            new KeyValuePair('recoveryCustomer', 'foobar', false),
            $params,
        )->next();
    }
}

/**
 * @internal
 */
class CustomerRecoveryDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'customer_recovery';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('hash', 'hash'))->addFlags(new Required()),
            (new FkField('customer_id', 'customerId', TestCustomerDefinition::class))->addFlags(new Required()),

            new OneToOneAssociationField(
                'customer',
                'customer_id',
                'id',
                TestCustomerDefinition::class,
                false
            ),
        ]);
    }
}

/**
 * @internal
 */
class TestCustomerDefinition extends EntityDefinition
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

            new OneToOneAssociationField(
                'recoveryCustomer',
                'id',
                'customer_id',
                CustomerRecoveryDefinition::class,
                false
            ),
        ]);
    }
}

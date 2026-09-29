<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderAddress;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderAddressDefinition::class)]
class OrderAddressDefinitionTest extends TestCase
{
    private OrderAddressDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [OrderAddressDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(OrderAddressDefinition::ENTITY_NAME);
        static::assertInstanceOf(OrderAddressDefinition::class, $definition);
        $this->definition = $definition;
    }

    public function testEntityName(): void
    {
        static::assertSame('order_address', $this->definition->getEntityName());
    }

    public function testEntityClass(): void
    {
        static::assertSame(OrderAddressEntity::class, $this->definition->getEntityClass());
    }

    public function testCollectionClass(): void
    {
        static::assertSame(OrderAddressCollection::class, $this->definition->getCollectionClass());
    }

    public function testSince(): void
    {
        static::assertSame('6.0.0.0', $this->definition->since());
    }

    public function testIdFieldIsPrimaryKey(): void
    {
        $field = $this->definition->getFields()->get('id');
        static::assertInstanceOf(IdField::class, $field);
        static::assertTrue($field->is(PrimaryKey::class));
        static::assertTrue($field->is(Required::class));
    }

    public function testOrderIdField(): void
    {
        $field = $this->definition->getFields()->get('orderId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testOrderVersionIdField(): void
    {
        $field = $this->definition->getFields()->get('orderVersionId');
        static::assertInstanceOf(ReferenceVersionField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testCountryIdField(): void
    {
        $field = $this->definition->getFields()->get('countryId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testFirstNameField(): void
    {
        $field = $this->definition->getFields()->get('firstName');
        static::assertInstanceOf(StringField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testOrderAssociation(): void
    {
        $field = $this->definition->getFields()->get('order');
        static::assertInstanceOf(ManyToOneAssociationField::class, $field);
    }
}

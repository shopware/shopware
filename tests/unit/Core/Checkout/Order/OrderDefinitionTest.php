<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderDefinition::class)]
class OrderDefinitionTest extends TestCase
{
    private OrderDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [OrderDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(OrderDefinition::ENTITY_NAME);
        static::assertInstanceOf(OrderDefinition::class, $definition);
        $this->definition = $definition;
    }

    public function testEntityName(): void
    {
        static::assertSame('order', $this->definition->getEntityName());
    }

    public function testEntityClass(): void
    {
        static::assertSame(OrderEntity::class, $this->definition->getEntityClass());
    }

    public function testCollectionClass(): void
    {
        static::assertSame(OrderCollection::class, $this->definition->getCollectionClass());
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

    public function testOrderNumberField(): void
    {
        $field = $this->definition->getFields()->get('orderNumber');
        static::assertInstanceOf(NumberRangeField::class, $field);
    }

    public function testCurrencyIdField(): void
    {
        $field = $this->definition->getFields()->get('currencyId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testOrderDateTimeField(): void
    {
        $field = $this->definition->getFields()->get('orderDateTime');
        static::assertInstanceOf(DateTimeField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testStateIdField(): void
    {
        $field = $this->definition->getFields()->get('stateId');
        static::assertInstanceOf(StateMachineStateField::class, $field);
        static::assertTrue($field->is(Required::class));
    }
}

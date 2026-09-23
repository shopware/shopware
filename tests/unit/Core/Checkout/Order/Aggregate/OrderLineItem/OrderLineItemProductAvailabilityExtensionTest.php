<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderLineItem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemProductAvailabilityExtension;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderLineItemProductAvailabilityExtension::class)]
class OrderLineItemProductAvailabilityExtensionTest extends TestCase
{
    public function testItExtendsOrderLineItems(): void
    {
        $extension = new OrderLineItemProductAvailabilityExtension();

        static::assertSame(OrderLineItemDefinition::ENTITY_NAME, $extension->getEntityName());
        static::assertSame(OrderLineItemDefinition::class, $extension->getDefinitionClass());
    }

    public function testProductAvailableIsAddedAsRuntimeField(): void
    {
        $field = $this->extendedField();

        static::assertSame('productAvailable', $field->getPropertyName());
        static::assertNotNull($field->getFlag(Runtime::class));
    }

    public function testProductAvailableIsReadableThroughTheStoreApi(): void
    {
        // StructEncoder drops extensions without an api aware field
        $flag = $this->extendedField()->getFlag(ApiAware::class);

        static::assertInstanceOf(ApiAware::class, $flag);
        static::assertTrue($flag->isSourceAllowed(SalesChannelApiSource::class));
    }

    private function extendedField(): Field
    {
        $fields = new FieldCollection();

        (new OrderLineItemProductAvailabilityExtension())->extendFields($fields);

        static::assertCount(1, $fields);
        $field = $fields->first();
        static::assertInstanceOf(Field::class, $field);

        return $field;
    }
}

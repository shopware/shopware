<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Tracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerCollection;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerDefinition;
use Shopware\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTrackingCustomerDefinition::class)]
class SalesChannelTrackingCustomerDefinitionTest extends TestCase
{
    private SalesChannelTrackingCustomerDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [SalesChannelTrackingCustomerDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGateway::class),
        );

        /** @var SalesChannelTrackingCustomerDefinition $definition */
        $definition = $registry->getByEntityName(SalesChannelTrackingCustomerDefinition::ENTITY_NAME);
        $this->definition = $definition;
    }

    public function testEntityName(): void
    {
        static::assertSame('sales_channel_tracking_customer', $this->definition->getEntityName());
    }

    public function testEntityClass(): void
    {
        static::assertSame(SalesChannelTrackingCustomerEntity::class, $this->definition->getEntityClass());
    }

    public function testCollectionClass(): void
    {
        static::assertSame(SalesChannelTrackingCustomerCollection::class, $this->definition->getCollectionClass());
    }

    public function testSince(): void
    {
        static::assertSame('6.7.9.0', $this->definition->since());
    }

    public function testIdFieldIsPrimaryKey(): void
    {
        $field = $this->definition->getFields()->get('id');
        static::assertInstanceOf(IdField::class, $field);
        static::assertTrue($field->is(PrimaryKey::class));
        static::assertTrue($field->is(Required::class));
    }

    public function testCustomerIdField(): void
    {
        $field = $this->definition->getFields()->get('customerId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testSalesChannelIdField(): void
    {
        $field = $this->definition->getFields()->get('salesChannelId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testCustomerAssociation(): void
    {
        $field = $this->definition->getFields()->get('customer');
        static::assertInstanceOf(OneToOneAssociationField::class, $field);
    }

    public function testSalesChannelAssociation(): void
    {
        $field = $this->definition->getFields()->get('salesChannel');
        static::assertInstanceOf(ManyToOneAssociationField::class, $field);
    }
}

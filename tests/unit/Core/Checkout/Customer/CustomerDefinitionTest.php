<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerDefinition::class)]
class CustomerDefinitionTest extends TestCase
{
    private CustomerDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CustomerDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(CustomerDefinition::ENTITY_NAME);
        static::assertInstanceOf(CustomerDefinition::class, $definition);
        $this->definition = $definition;
    }

    public function testEntityName(): void
    {
        static::assertSame('customer', $this->definition->getEntityName());
    }

    public function testEntityClass(): void
    {
        static::assertSame(CustomerEntity::class, $this->definition->getEntityClass());
    }

    public function testCollectionClass(): void
    {
        static::assertSame(CustomerCollection::class, $this->definition->getCollectionClass());
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

    public function testGroupIdField(): void
    {
        $field = $this->definition->getFields()->get('groupId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testSalesChannelIdField(): void
    {
        $field = $this->definition->getFields()->get('salesChannelId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testEmailField(): void
    {
        $field = $this->definition->getFields()->get('email');
        static::assertInstanceOf(EmailField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testTheVatIdCountryIsReadableThroughTheAdminApiOnly(): void
    {
        foreach (['vatIdCountryId', 'vatIdCountry'] as $fieldName) {
            $field = $this->definition->getFields()->get($fieldName);
            static::assertNotNull($field, $fieldName);

            $flag = $field->getFlag(ApiAware::class);
            static::assertInstanceOf(ApiAware::class, $flag, $fieldName);
            static::assertTrue($flag->isSourceAllowed(AdminApiSource::class), $fieldName);
            static::assertFalse($flag->isSourceAllowed(SalesChannelApiSource::class), $fieldName);
        }
    }

    public function testAddressesAssociation(): void
    {
        $field = $this->definition->getFields()->get('addresses');
        static::assertInstanceOf(OneToManyAssociationField::class, $field);
    }
}

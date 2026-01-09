<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(SalesChannelCustomerAddressDefinition::class)]
#[Package('checkout')]
class SalesChannelCustomerAddressDefinitionTest extends TestCase
{
    public function testProcessCriteria(): void
    {
        $definition = new SalesChannelCustomerAddressDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        static::assertNotEmpty($criteria->getFilters());

        $filter = $criteria->getFilters()[0] ?? null;
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame('customerId', $filter->getField());
        static::assertSame($context->getCustomer()?->getId(), $filter->getValue());
    }

    public function testDefineFields(): void
    {
        $definition = new SalesChannelCustomerAddressDefinition();

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGateway::class),
        );

        $definition->compile($registry);
        $fields = $definition->getFields();

        $billingField = $fields->get('isDefaultBillingAddress');
        static::assertInstanceOf(BoolField::class, $billingField);
        static::assertTrue($billingField->is(Runtime::class));
        static::assertTrue($billingField->is(ApiAware::class));

        $shippingField = $fields->get('isDefaultShippingAddress');
        static::assertInstanceOf(BoolField::class, $shippingField);
        static::assertTrue($shippingField->is(Runtime::class));
        static::assertTrue($shippingField->is(ApiAware::class));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ApiProtectionException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\RuntimeFieldInCriteriaException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiCriteriaValidator::class)]
class ApiCriteriaValidatorTest extends TestCase
{
    /**
     * @param class-string<\Exception>|null $expectedException
     */
    #[DataProvider('criteriaProvider')]
    public function testCriteria(Criteria $criteria, Context $context, ?string $expectedException): void
    {
        $validator = new ApiCriteriaValidator(
            new StaticDefinitionInstanceRegistry(
                [
                    SalesChannelProductDefinition::class,
                    OrderLineItemDefinition::class,
                    ProductManufacturerDefinition::class,
                    ProductCategoryDefinition::class,
                    CategoryDefinition::class,
                ],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class)
            )
        );

        $e = null;

        try {
            $validator->validate('product', $criteria, $context);
        } catch (\Exception $e) {
        }

        if (!$expectedException) {
            static::assertNull($e);
        } else {
            static::assertInstanceOf($expectedException, $e);
        }
    }

    /**
     * @return iterable<string, array{Context}>
     */
    public static function privilegedContextProvider(): iterable
    {
        yield 'Admin API' => [new Context(new AdminApiSource('test'))];
        yield 'System' => [Context::createDefaultContext()];
    }

    #[DataProvider('privilegedContextProvider')]
    public function testAggregationFieldsAreValidatedForPrivilegedSources(Context $context): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductManufacturerDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = $registry->getByEntityName('product_manufacturer');
        $field = $definition->getField('id');
        static::assertInstanceOf(Field::class, $field);
        $field->removeFlag(ApiAware::class);

        $criteria = (new Criteria())->addAggregation(new EntityAggregation('manufacturers', 'id', 'product_manufacturer'));

        $this->expectExceptionObject(DataAbstractionLayerException::accessorNotAllowedInApiScope('id'));

        (new ApiCriteriaValidator($registry))->validate('product_manufacturer', $criteria, $context);
    }

    public static function criteriaProvider(): \Generator
    {
        $store = new Context(new ShopApiSource('test'));
        $sales = new Context(new SalesChannelApiSource('test'));
        $system = new Context(new SystemSource());
        $admin = new Context(new AdminApiSource('test'));

        yield 'Test order line item access in store api' => [
            (new Criteria())->addFilter(new EqualsFilter('orderLineItems.id', 1)),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Test order line item access in sales channel api' => [
            (new Criteria())->addFilter(new EqualsFilter('orderLineItems.id', 1)),
            $sales,
            ApiProtectionException::class,
        ];

        yield 'Allow price sorting in store api' => [
            (new Criteria())->addSorting(new FieldSorting('price')),
            $store,
            null,
        ];

        yield 'Allow cheapest price sorting in store api' => [
            (new Criteria())->addSorting(new FieldSorting('cheapestPrice')),
            $store,
            null,
        ];

        yield 'Allow price filtering in store api' => [
            (new Criteria())->addFilter(new RangeFilter('price', ['gt' => 10])),
            $store,
            null,
        ];

        yield 'Allow avg price aggregation in store api' => [
            (new Criteria())->addAggregation(new AvgAggregation('avg', 'price')),
            $store,
            null,
        ];

        yield 'Test order line item access in system scope' => [
            (new Criteria())->addFilter(new EqualsFilter('orderLineItems.id', 1)),
            $system,
            null,
        ];

        yield 'Test order line item access in admin api' => [
            (new Criteria())->addFilter(new EqualsFilter('orderLineItems.id', 1)),
            $admin,
            null,
        ];

        yield 'Test post-filter order line item access in store api' => [
            (new Criteria())->addPostFilter(new EqualsFilter('orderLineItems.id', 1)),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Test sorting order line item access in store api' => [
            (new Criteria())->addSorting(new FieldSorting('orderLineItems.id')),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Test query order line item access in store api' => [
            (new Criteria())->addQuery(new ScoreQuery(new EqualsFilter('orderLineItems.id', 1), 100)),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Test aggregation order line item access in store api' => [
            (new Criteria())->addAggregation(new TermsAggregation('agg', 'orderLineItems.id')),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Allow entity aggregation over a foreign key in store api' => [
            (new Criteria())->addAggregation(new EntityAggregation('manufacturers', 'manufacturerId', 'product_manufacturer')),
            $store,
            null,
        ];

        yield 'Allow entity aggregation over the own id in store api' => [
            (new Criteria())->addAggregation(new EntityAggregation('products', 'id', 'product')),
            $store,
            null,
        ];

        yield 'Allow entity aggregation over a many to many id list in store api' => [
            (new Criteria())->addAggregation(new EntityAggregation('categories', 'categoryIds', 'category')),
            $store,
            null,
        ];

        yield 'Reject entity aggregation loading another entity through a foreign key in store api' => [
            (new Criteria())->addAggregation(new EntityAggregation('customers', 'manufacturerId', 'customer')),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Reject entity aggregation loading another entity through an id in store api' => [
            (new Criteria())->addAggregation(new EntityAggregation('customers', 'id', 'customer')),
            $sales,
            ApiProtectionException::class,
        ];

        yield 'Reject entity aggregation nested in a filter aggregation in store api' => [
            (new Criteria())->addAggregation(new FilterAggregation('filtered', new EntityAggregation('customers', 'manufacturerId', 'customer'), [new EqualsFilter('active', true)])),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Allow entity aggregation loading another entity in admin api' => [
            (new Criteria())->addAggregation(new EntityAggregation('customers', 'manufacturerId', 'customer')),
            $admin,
            null,
        ];

        yield 'Allow entity aggregation loading another entity in system scope' => [
            (new Criteria())->addAggregation(new EntityAggregation('manufacturers', 'id', 'product_manufacturer')),
            $system,
            null,
        ];

        yield 'Test filter for runtime field' => [
            (new Criteria())->addFilter(new ContainsFilter('variation', Uuid::randomHex())),
            $admin,
            RuntimeFieldInCriteriaException::class,
        ];

        yield 'Test aggregate for runtime field' => [
            (new Criteria())->addAggregation(new TermsAggregation('agg', 'variation')),
            $admin,
            RuntimeFieldInCriteriaException::class,
        ];
    }
}

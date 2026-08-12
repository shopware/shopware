<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SalesChannelProductDefinition::class)]
class SalesChannelProductDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(SalesChannelProductEntity::class, $definition->getEntityClass());
        static::assertSame(SalesChannelProductCollection::class, $definition->getCollectionClass());
    }

    public function testDefinesTheRuntimeCalculationFields(): void
    {
        $fields = $this->createDefinition()->getFields();

        foreach (['calculatedPrice', 'calculatedPrices', 'calculatedMaxPurchase', 'calculatedCheapestPrice', 'isNew', 'cheapestPrice', 'cheapestPriceContainer', 'sortedProperties', 'measurements'] as $field) {
            static::assertNotNull($fields->get($field), $field . ' must be defined');
        }
    }

    public function testProcessCriteriaAddsTheAvailableFilterAndDefaultAssociations(): void
    {
        $criteria = new Criteria();

        $this->createDefinition()->processCriteria($criteria, static::createStub(SalesChannelContext::class));

        $availableFilters = array_filter($criteria->getFilters(), static fn ($f) => $f instanceof ProductAvailableFilter);
        static::assertCount(1, $availableFilters);

        foreach (['prices', 'unit', 'deliveryTime', 'cover', 'tax'] as $association) {
            static::assertTrue($criteria->hasAssociation($association), $association . ' association expected');
        }
    }

    public function testProcessCriteriaKeepsAnExistingAvailableFilter(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new ProductAvailableFilter('sales-channel-id'));

        $this->createDefinition()->processCriteria($criteria, static::createStub(SalesChannelContext::class));

        $availableFilters = array_filter($criteria->getFilters(), static fn ($f) => $f instanceof ProductAvailableFilter);
        static::assertCount(1, $availableFilters);
    }

    public function testProcessCriteriaFiltersProductReviewsToActiveOnes(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('productReviews');

        $this->createDefinition()->processCriteria($criteria, static::createStub(SalesChannelContext::class));

        $filters = $criteria->getAssociation('productReviews')->getFilters();
        static::assertCount(1, $filters);
    }

    public function testProcessCriteriaSkipsAssociationSetupBelowRootNestingLevel(): void
    {
        $criteria = new Criteria(nestingLevel: 1);

        $this->createDefinition()->processCriteria($criteria, static::createStub(SalesChannelContext::class));

        static::assertFalse($criteria->hasAssociation('prices'));
    }

    private function createDefinition(): SalesChannelProductDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [SalesChannelProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(SalesChannelProductDefinition::ENTITY_NAME);
        static::assertInstanceOf(SalesChannelProductDefinition::class, $definition);

        return $definition;
    }

    #[DataProvider('nestingLevelProvider')]
    public function testProcessCriteriaFiltersInactiveReviewsOnEveryNestingLevel(int $nestingLevel): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria(nestingLevel: $nestingLevel);
        $criteria->addAssociation('productReviews');
        $context = Generator::generateSalesChannelContext(overrides: ['customer' => null]);

        $definition->processCriteria($criteria, $context);

        static::assertEquals(
            [new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)])],
            $criteria->getAssociation('productReviews')->getFilters()
        );
    }

    #[DataProvider('nestingLevelProvider')]
    public function testProcessCriteriaAllowsOwnReviewsOnEveryNestingLevel(int $nestingLevel): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria(nestingLevel: $nestingLevel);
        $criteria->addAssociation('productReviews');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext(customer: $customer);

        $definition->processCriteria($criteria, $context);

        static::assertEquals(
            [new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('status', true),
                new EqualsFilter('customerId', $customer->getId()),
            ])],
            $criteria->getAssociation('productReviews')->getFilters()
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nestingLevelProvider(): iterable
    {
        yield 'root level' => [Criteria::ROOT_NESTING_LEVEL];
        yield 'first association level' => [1];
        yield 'second association level' => [2];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(SalesChannelProductDefinition::class)]
class SalesChannelProductDefinitionTest extends TestCase
{
    public function testProcessCriteriaRootLevel(): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        static::assertNotEmpty($criteria->getAssociations());
        static::assertTrue($criteria->hasAssociation('prices'));
        static::assertTrue($criteria->hasAssociation('unit'));
        static::assertTrue($criteria->hasAssociation('deliveryTime'));
        static::assertTrue($criteria->hasAssociation('cover'));

        static::assertNotEmpty($criteria->getFilters());
    }

    public function testProcessCriteriaAssociationLevel(): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria(nestingLevel: 1);
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        static::assertEmpty($criteria->getAssociations());

        static::assertNotEmpty($criteria->getFilters());
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

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
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

    #[DataProvider('reviewAssociationNestingLevelProvider')]
    public function testProcessCriteriaHidesUnapprovedReviewsOnEveryNestingLevel(int $nestingLevel): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria(nestingLevel: $nestingLevel);
        $criteria->addAssociation('productReviews');
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        static::assertEquals(
            [new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('status', true),
                new EqualsFilter('customerId', Generator::CUSTOMER),
            ])],
            $criteria->getAssociation('productReviews')->getFilters()
        );
    }

    public static function reviewAssociationNestingLevelProvider(): \Generator
    {
        yield 'reviews requested at the root of the criteria' => [Criteria::ROOT_NESTING_LEVEL];
        yield 'reviews nested one level deeper, e.g. below the children association' => [1];
        yield 'reviews nested arbitrarily deep' => [3];
    }
}

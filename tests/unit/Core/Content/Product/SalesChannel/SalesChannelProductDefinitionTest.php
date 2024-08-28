<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Test\Generator;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition
 */
class SalesChannelProductDefinitionTest extends TestCase
{
    public function testProcessCriteriaRootLevel(): void
    {
        $definition = new SalesChannelProductDefinition();
        $criteria = new Criteria();
        $context = Generator::createSalesChannelContext();

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
        $context = Generator::createSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        static::assertEmpty($criteria->getAssociations());

        static::assertNotEmpty($criteria->getFilters());
    }
}

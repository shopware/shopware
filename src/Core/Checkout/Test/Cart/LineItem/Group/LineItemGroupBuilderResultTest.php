<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilderResult;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class LineItemGroupBuilderResultTest extends TestCase
{
    /**
     * This test verifies that our functions does
     * correctly return false if we dont have any existing entries.
     *
     * @group lineitemgroup
     */
    public function testHasItemsOnEmptyList(): void
    {
        $result = new LineItemGroupBuilderResult();

        static::assertFalse($result->hasFoundItems());
    }

    /**
     * This test verifies that we really search for items
     * in our hasFoundItems function.
     * If we have found groups, but no items in there, it should
     * also return FALSE.
     *
     * @group lineitemgroup
     */
    public function testHasItemsOnGroupWithNoResults(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        $group = new LineItemGroup();

        $result = new LineItemGroupBuilderResult();
        $result->addGroup($groupDefinition, $group);

        static::assertFalse($result->hasFoundItems());
    }

    /**
     * This test verifies that we get TRUE
     * if we have existing entries.
     *
     * @group lineitemgroup
     */
    public function testHasItemsIfExisting(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        $group = new LineItemGroup();
        $group->addItem('ID1', 2);
        $group->addItem('ID2', 1);

        $result = new LineItemGroupBuilderResult();
        $result->addGroup($groupDefinition, $group);

        static::assertTrue($result->hasFoundItems());
    }

    /**
     * This test verifies that our result of a
     * group definition uses the item IDs as keys in the array
     *
     * @group lineitemgroup
     */
    public function testGroupTotalResultUsesKeys(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        $group = new LineItemGroup();
        $group->addItem('ID1', 2);

        $result = new LineItemGroupBuilderResult();
        $result->addGroup($groupDefinition, $group);

        static::assertArrayHasKey('ID1', $result->getGroupTotalResult($groupDefinition));
    }

    /**
     * This test verifies that we can add
     * a single line item for a definition and retrieve
     * all the aggregated data with our total result function
     *
     * @group lineitemgroup
     */
    public function testGroupTotalContainsItem(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        $group = new LineItemGroup();
        $group->addItem('ID1', 2);

        $result = new LineItemGroupBuilderResult();
        $result->addGroup($groupDefinition, $group);

        $data = $result->getGroupTotalResult($groupDefinition);

        $itemQuantity = $result->getGroupTotalResult($groupDefinition)['ID1'];

        static::assertCount(1, $data);
        static::assertEquals('ID1', $itemQuantity->getLineItemId());
        static::assertEquals(2, $itemQuantity->getQuantity());
    }

    /**
     * This test verifies that we can add
     * a group of multiple line items for a definition and retrieve
     * all the aggregated data with our total result function
     *
     * @group lineitemgroup
     */
    public function testGroupTotalContainsAllGroupItems(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        $group = new LineItemGroup();
        $group->addItem('ID1', 2);
        $group->addItem('ID2', 1);

        $result = new LineItemGroupBuilderResult();
        $result->addGroup($groupDefinition, $group);

        $data = $result->getGroupTotalResult($groupDefinition);

        static::assertCount(2, $data);

        static::assertEquals('ID1', $result->getGroupTotalResult($groupDefinition)['ID1']->getLineItemId());
        static::assertEquals(2, $result->getGroupTotalResult($groupDefinition)['ID1']->getQuantity());

        static::assertEquals('ID2', $result->getGroupTotalResult($groupDefinition)['ID2']->getLineItemId());
        static::assertEquals(1, $result->getGroupTotalResult($groupDefinition)['ID2']->getQuantity());
    }

    /**
     * This test verifies that our quantities are
     * increased if we already have the line items in
     * the result of our provided group definition.
     *
     * @group lineitemgroup
     */
    public function testQuantityIncreasedOnExistingItems(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        $result = new LineItemGroupBuilderResult();

        $group1 = new LineItemGroup();
        $group1->addItem('ID1', 2);

        $group2 = new LineItemGroup();
        $group2->addItem('ID1', 3);

        $result->addGroup($groupDefinition, $group1);
        $result->addGroup($groupDefinition, $group2);

        static::assertEquals(5, $result->getGroupTotalResult($groupDefinition)['ID1']->getQuantity());
    }

    /**
     * This test verifies that we get an empty array
     * and no exception if we try to retrieve the result
     * of a group definition that has not even been found.
     *
     * @group lineitemgroup
     */
    public function testUnknownGroupDefinitionReturnsEmptyArray(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'UNKNOWN123', 2, 'PRICE_ASC', new RuleCollection());

        $result = new LineItemGroupBuilderResult();

        static::assertCount(0, $result->getGroupTotalResult($groupDefinition));
    }

    /**
     * This test verifies that whenever we add a found group
     * to a group definition, the result increases the found-count.
     * In the end, we should not only have aggregated values, but also
     * know how many times a group has been found.
     *
     * @group lineitemgroup
     */
    public function testGroupCountsAreAdded(): void
    {
        $groupDefinition1 = new LineItemGroupDefinition('ID1', 'UNKNOWN', 2, 'PRICE_ASC', new RuleCollection());
        $groupDefinition2 = new LineItemGroupDefinition('ID2', 'UNKNOWN2', 2, 'PRICE_ASC', new RuleCollection());

        $result = new LineItemGroupBuilderResult();

        $group1 = new LineItemGroup();
        $group1->addItem('ID1', 2);

        $group2 = new LineItemGroup();
        $group2->addItem('ID1', 3);

        $group3 = new LineItemGroup();
        $group3->addItem('ID2', 1);

        $result->addGroup($groupDefinition1, $group1);
        $result->addGroup($groupDefinition1, $group2);
        $result->addGroup($groupDefinition2, $group3);

        static::assertEquals(2, $result->getGroupCount($groupDefinition1));
        static::assertEquals(1, $result->getGroupCount($groupDefinition2));
    }

    /**
     * This test verifies that we get a result of 0
     * found groups if we search for a group definition on
     * an empty result object.
     *
     * @group lineitemgroup
     */
    public function testGroupCountsOnEmptyData(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'UNKNOWN', 2, 'PRICE_ASC', new RuleCollection());

        $result = new LineItemGroupBuilderResult();

        static::assertEquals(0, $result->getGroupCount($groupDefinition));
    }

    /**
     * This test verifies that our list of group results
     * for a group definition returns an empty list,
     * if no result itesm exist.
     *
     * @group lineitemgroup
     */
    public function testGroupResultOnEmptyData(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'UNKNOWN', 2, 'PRICE_ASC', new RuleCollection());

        $result = new LineItemGroupBuilderResult();

        static::assertCount(0, $result->getGroupResult($groupDefinition));
    }

    /**
     * This test verifies that we get each single group result of a given group definition.
     * So our definition is being found 2 times with each 2 line items and their quantities.
     *
     * This is used to identify each group package later on and
     * allows us to e.g. only use the first valid group for discounts
     * instead of all found groups for a definition.
     *
     * @group lineitemgroup
     */
    public function testGroupResultHasAllFoundGroupsOfDefinition(): void
    {
        $groupDefinition = new LineItemGroupDefinition('ID1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        $group1 = new LineItemGroup();
        $group1->addItem('ID1', 2);
        $group1->addItem('ID2', 1);

        $group2 = new LineItemGroup();
        $group2->addItem('ID1', 3);
        $group2->addItem('ID3', 2);

        $result = new LineItemGroupBuilderResult();
        $result->addGroup($groupDefinition, $group1);
        $result->addGroup($groupDefinition, $group2);

        $data = $result->getGroupResult($groupDefinition);

        static::assertCount(2, $data);

        static::assertEquals('ID1', $data[0]->getItems()[0]->getLineItemId());
        static::assertEquals(2, $data[0]->getItems()[0]->getQuantity());
        static::assertEquals('ID2', $data[0]->getItems()[1]->getLineItemId());
        static::assertEquals(1, $data[0]->getItems()[1]->getQuantity());

        static::assertEquals('ID1', $data[1]->getItems()[0]->getLineItemId());
        static::assertEquals(3, $data[1]->getItems()[0]->getQuantity());
        static::assertEquals('ID3', $data[1]->getItems()[1]->getLineItemId());
        static::assertEquals(2, $data[1]->getItems()[1]->getQuantity());
    }
}

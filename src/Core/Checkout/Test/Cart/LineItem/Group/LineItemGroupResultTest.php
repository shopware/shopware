<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupResult;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Content\Rule\RuleCollection;

class LineItemGroupResultTest extends TestCase
{
    use LineItemTestFixtureBehaviour;

    /**
     * This test verifies that the group
     * property is assigned and returned correctly.
     *
     * @test
     * @group lineitemgroup
     */
    public function testGroupDefinitionProperty()
    {
        $group = new LineItemGroupDefinition(
            '',
            9,
            '',
            new RuleCollection()
        );

        $result = new LineItemGroupResult($group, new LineItemCollection());

        static::assertSame($group, $result->getGroupDefinition());
    }

    /**
     * This test verifies that the items result
     * property is assigned and returned correctly.
     *
     * @test
     * @group lineitemgroup
     */
    public function testItemsResultProperty()
    {
        $group = new LineItemGroupDefinition(
            '',
            1,
            '',
            new RuleCollection()
        );

        $product = $this->createProductItem(50, 19);

        $result = new LineItemGroupResult($group, new LineItemCollection([$product]));

        static::assertSame($product, $result->getLineItems()->getFlat()[0]);
    }
}

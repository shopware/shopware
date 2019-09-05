<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\RulesTestFixtureBehaviour;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class LineItemGroupDefinitionTest extends TestCase
{
    use RulesTestFixtureBehaviour;

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     *
     * @test
     * @group lineitemgroup
     */
    public function testPackagerKeyProperty(): void
    {
        $group = new LineItemGroupDefinition('COUNT', 2, 'PRICE_ASC', new RuleCollection());

        static::assertEquals('COUNT', $group->getPackagerKey());
    }

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     *
     * @test
     * @group lineitemgroup
     */
    public function testValueProperty(): void
    {
        $group = new LineItemGroupDefinition('COUNT', 2, 'PRICE_ASC', new RuleCollection());

        static::assertEquals(2, $group->getValue());
    }

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     *
     * @test
     * @group lineitemgroup
     */
    public function tesSorterKeyProperty(): void
    {
        $group = new LineItemGroupDefinition('COUNT', 2, 'PRICE_ASC', new RuleCollection());

        static::assertEquals('PRICE_ASC', $group->getSorterKey());
    }

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     *
     * @test
     * @group lineitemgroup
     */
    public function testRulesProperty(): void
    {
        $ruleEntity = $this->buildRuleEntity(
            $this->getMinQuantityRule(Uuid::randomBytes(), 2)
        );

        $ruleCollection = new RuleCollection([$ruleEntity]);

        $group = new LineItemGroupDefinition('COUNT', 2, 'PRICE_ASC', $ruleCollection);

        static::assertSame($ruleCollection, $group->getRules());
    }
}

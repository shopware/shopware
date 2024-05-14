<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\LineItem\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\Traits\RulesTestFixtureBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LineItemGroupDefinition::class)]
class LineItemGroupDefinitionTest extends TestCase
{
    use RulesTestFixtureBehaviour;

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     * We have to use an id property to be able to differ
     * between groups that might have the same configuration.
     * The id can be any random string, or the id from
     * an entity object, if built from that one.
     */
    #[Group('lineitemgroup')]
    public function testPackagerKeyId(): void
    {
        $group = new LineItemGroupDefinition('ID-1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        static::assertEquals('ID-1', $group->getId());
    }

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     */
    #[Group('lineitemgroup')]
    public function testPackagerKeyProperty(): void
    {
        $group = new LineItemGroupDefinition('ID-1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        static::assertEquals('COUNT', $group->getPackagerKey());
    }

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     */
    #[Group('lineitemgroup')]
    public function testValueProperty(): void
    {
        $group = new LineItemGroupDefinition('ID-1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        static::assertEquals(2, $group->getValue());
    }

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     */
    #[Group('lineitemgroup')]
    public function tesSorterKeyProperty(): void
    {
        $group = new LineItemGroupDefinition('ID-1', 'COUNT', 2, 'PRICE_ASC', new RuleCollection());

        static::assertEquals('PRICE_ASC', $group->getSorterKey());
    }

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     */
    #[Group('lineitemgroup')]
    public function testRulesProperty(): void
    {
        $ruleEntity = $this->buildRuleEntity(
            $this->getMinQuantityRule(Uuid::randomBytes(), 2)
        );

        $ruleCollection = new RuleCollection([$ruleEntity]);

        $group = new LineItemGroupDefinition('ID-1', 'COUNT', 2, 'PRICE_ASC', $ruleCollection);

        static::assertSame($ruleCollection, $group->getRules());
    }
}

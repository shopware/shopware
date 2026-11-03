<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\RuleCollection;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\Framework\Rule\UnknownConditionRule;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleCollection::class)]
class RuleCollectionTest extends TestCase
{
    public function testCollectsUnknownConditionsWhenCollectingNestedRules(): void
    {
        $original = new AndRule([new OrRule([new SimpleRule(), new TrueRule()])]);

        // Replace the class name with an unavailable name of the same length to keep the serialized data valid.
        $serialized = str_replace(SimpleRule::class, 'Shopware\Core\Framework\Rule\AbsentRule', serialize($original));
        $rule = Serialization::assertUnserializedInstanceOf(AndRule::class, $serialized);
        $nested = $rule->getRules()[0];
        static::assertInstanceOf(OrRule::class, $nested);
        static::assertInstanceOf(UnknownConditionRule::class, $nested->getRules()[0]);

        $collection = new RuleCollection([$rule]);

        static::assertTrue($collection->has(AndRule::class));
        static::assertTrue($collection->has(OrRule::class));
        static::assertTrue($collection->has(TrueRule::class));
        static::assertTrue($collection->has(UnknownConditionRule::class));
        static::assertFalse($collection->has(SimpleRule::class));
        static::assertCount(1, $collection->filterInstance(TrueRule::class));
    }
}

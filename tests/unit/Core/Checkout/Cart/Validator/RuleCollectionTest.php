<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\RuleCollection;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[CoversClass(RuleCollection::class)]
class RuleCollectionTest extends TestCase
{
    public function testMetaCollecting(): void
    {
        $collection = new RuleCollection([
            new TrueRule(),
            new AndRule([
                new TrueRule(),
                new OrRule([
                    new TrueRule(),
                    new FalseRule(),
                ]),
            ]),
        ]);

        static::assertTrue($collection->has(FalseRule::class));
        static::assertTrue($collection->has(OrRule::class));
        static::assertEquals(
            new RuleCollection([
                new FalseRule(),
            ]),
            $collection->filterInstance(FalseRule::class)
        );
    }
}

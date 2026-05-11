<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\ShippingMethodRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleConstraints;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingMethodRule::class)]
#[Group('rules')]
class ShippingMethodRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $rule = new ShippingMethodRule();

        static::assertEquals([
            'shippingMethodIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ], $rule->getConstraints());
    }
}

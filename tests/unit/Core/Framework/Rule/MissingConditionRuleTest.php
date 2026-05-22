<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\MissingConditionRule;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(MissingConditionRule::class)]
class MissingConditionRuleTest extends TestCase
{
    public function testMatchReturnsFalse(): void
    {
        $rule = new MissingConditionRule('unknownPluginRule');

        static::assertFalse($rule->match($this->createMock(RuleScope::class)));
    }

    public function testGetConstraintsIsEmpty(): void
    {
        $rule = new MissingConditionRule('unknownPluginRule');

        static::assertSame([], $rule->getConstraints());
    }

    public function testPreservesOriginalName(): void
    {
        $rule = new MissingConditionRule('unknownPluginRule');

        static::assertSame('unknownPluginRule', $rule->getOriginalName());
    }
}

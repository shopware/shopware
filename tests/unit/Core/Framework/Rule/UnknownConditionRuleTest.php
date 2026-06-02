<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\UnknownConditionRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(UnknownConditionRule::class)]
class UnknownConditionRuleTest extends TestCase
{
    public function testMatchReturnsFalse(): void
    {
        $rule = new UnknownConditionRule(['_name' => 'unknownPluginRule']);

        static::assertFalse($rule->match($this->createMock(RuleScope::class)));
    }

    public function testGetConstraintsIsEmpty(): void
    {
        $rule = new UnknownConditionRule(['_name' => 'unknownPluginRule']);

        static::assertSame([], $rule->getConstraints());
    }

    public function testPreservesOriginalName(): void
    {
        $rule = new UnknownConditionRule(['_name' => 'unknownPluginRule', 'operator' => Rule::OPERATOR_EQ]);

        static::assertSame('unknownPluginRule', $rule->getOriginalName());
    }

    public function testGetOriginalNameIsEmptyWhenPayloadHasNoName(): void
    {
        static::assertSame('', (new UnknownConditionRule())->getOriginalName());
    }

    public function testJsonSerializeReEmitsOriginalPayloadVerbatim(): void
    {
        $payload = [
            '_name' => 'unknownPluginRule',
            'operator' => Rule::OPERATOR_EQ,
            'identifiers' => ['foo', 'bar'],
        ];

        $rule = new UnknownConditionRule($payload);

        static::assertSame($payload, $rule->jsonSerialize());
    }
}

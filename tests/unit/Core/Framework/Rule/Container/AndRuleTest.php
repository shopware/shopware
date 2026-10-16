<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(AndRule::class)]
class AndRuleTest extends TestCase
{
    #[DataProvider('cases')]
    public function testRuleLogic(AndRule $rule, bool $matching): void
    {
        $scope = static::createStub(RuleScope::class);
        static::assertSame($matching, $rule->match($scope));
    }

    public function testAndRuleNameIsStillTheSame(): void
    {
        static::assertSame('andContainer', (new AndRule())->getName());
    }

    public static function cases(): \Generator
    {
        yield 'Test with single matching rule' => [
            new AndRule([new TrueRule()]),
            true,
        ];

        yield 'Test with multiple matching rule' => [
            new AndRule([
                new TrueRule(),
                new TrueRule(),
            ]),
            true,
        ];

        yield 'Test with single not matching rule' => [
            new AndRule([new FalseRule()]),
            false,
        ];

        yield 'Test with multiple not matching rule' => [
            new AndRule([
                new TrueRule(),
                new FalseRule(),
            ]),
            false,
        ];

        yield 'Test with matching and not matching rule' => [
            new AndRule([
                new TrueRule(),
                new FalseRule(),
            ]),
            false,
        ];
    }
}

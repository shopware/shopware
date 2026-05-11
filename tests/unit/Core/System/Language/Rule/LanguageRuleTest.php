<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Language\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageException;
use Shopware\Core\System\Language\Rule\LanguageRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(LanguageRule::class)]
class LanguageRuleTest extends TestCase
{
    public function testConstraints(): void
    {
        $ruleConstraints = (new LanguageRule())->getConstraints();

        static::assertEquals([
            'operator' => RuleConstraints::uuidOperators(false),
            'languageIds' => RuleConstraints::uuids(),
        ], $ruleConstraints);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $languageId): void
    {
        $languageIds = ['kyln123', 'kyln456'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);

        $salesChannelContext->method('getContext')->willReturn($context);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule = (new LanguageRule())->assign(['languageIds' => $languageIds, 'operator' => $operator]);

        $match = $rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function getMatchValues(): array
    {
        return [
            'operator_eq / not match / language id' => [Rule::OPERATOR_EQ, false, Uuid::randomHex()],
            'operator_eq / match / language id' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / language id' => [Rule::OPERATOR_NEQ, true,  Uuid::randomHex()],
            'operator_neq / not match / language id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
        ];
    }

    public function testCallingMatchWithoutValueThrowsException(): void
    {
        $this->expectExceptionObject(LanguageException::unsupportedValue(\gettype(null), LanguageRule::class));
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule = new LanguageRule(Rule::OPERATOR_EQ, null);
        $rule->match($scope);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\SalesChannelRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @package business-ops
 *
 * @internal
 * @covers \Shopware\Core\Framework\Rule\SalesChannelRule
 */
class SalesChannelRuleTest extends TestCase
{
    /**
     * @dataProvider provideTestData
     *
     * @param string[] $salesChannelIds
     */
    public function testMatchesWithCorrectSalesChannel(string $operator, string $currentSalesChannel, ?array $salesChannelIds, bool $expected): void
    {
        $ruleScope = $this->createRuleScope($currentSalesChannel);

        $salesChannelRule = new SalesChannelRule($operator, $salesChannelIds);

        static::assertSame($expected, $salesChannelRule->match($ruleScope));
    }

    public function provideTestData(): \Generator
    {
        yield 'matches with correct sales channel' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test')],
            true,
        ];

        yield 'matches with wrong sales channel' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test1')],
            false,
        ];

        yield 'matches with multiple sales channel' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test1'), Uuid::fromStringToHex('test'), Uuid::fromStringToHex('test2')],
            true,
        ];

        yield 'matches not equal with valid sales channel' => [
            Rule::OPERATOR_NEQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test1')],
            true,
        ];

        yield 'matches not equal with invalid sales channel' => [
            Rule::OPERATOR_NEQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test')],
            false,
        ];

        yield 'matches with empty sales channel ids' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [],
            false,
        ];

        yield 'matches with null channel ids' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            null,
            false,
        ];
    }

    public function testProvidesConstraints(): void
    {
        $salesChannelRule = new SalesChannelRule(RUle::OPERATOR_EQ, []);
        $constraints = $salesChannelRule->getConstraints();

        static::assertArrayHasKey('salesChannelIds', $constraints);
        static::assertEquals(RuleConstraints::uuids(), $constraints['salesChannelIds']);

        static::assertArrayHasKey('operator', $constraints);
        static::assertEquals(RuleConstraints::uuidOperators(false), $constraints['operator']);
    }

    private function createRuleScope(string $salesChannelId): RuleScope
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        return new CheckoutRuleScope(
            $salesChannelContext
        );
    }
}

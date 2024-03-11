<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerAgeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerAgeRule::class)]
#[Group('rules')]
class CustomerAgeRuleTest extends TestCase
{
    private CustomerAgeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CustomerAgeRule();
    }

    public function testGetName(): void
    {
        static::assertSame('customerAge', $this->rule->getName());
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        $this->expectException(UnsupportedValueException::class);
        $this->rule->assign([
            'operator' => Rule::OPERATOR_EQ,
            'age' => null,
        ]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $customer = new CustomerEntity();
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $this->rule->match(new CheckoutRuleScope($salesChannelContext));
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testIfMatchesCorrect(
        ?string $birthday,
        string $operator,
        ?int $age,
        bool $expected
    ): void {
        $this->rule->assign([
            'operator' => $operator,
            'age' => $age,
        ]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $customer = new CustomerEntity();

        if ($birthday) {
            $birthday = new \DateTimeImmutable($birthday);
            $customer->setBirthday($birthday);
        }

        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $match = $this->rule->match(new CheckoutRuleScope($salesChannelContext));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getCaseTestMatchValues(): \Traversable
    {
        $birthday = new \DateTime('1991/10/16');
        $now = new \DateTime();

        $correctAge = $now->diff($birthday)->y;
        $wrongAge = $correctAge - 2;

        yield 'equal / match' => ['1991/10/16', Rule::OPERATOR_EQ, $correctAge, true];
        yield 'equal / no match' => ['1991/10/16', Rule::OPERATOR_EQ, $wrongAge, false];
        yield 'equal / fallback no match' => [null, Rule::OPERATOR_EQ, $correctAge, false];
        yield 'not equal / match' => ['1991/10/16', Rule::OPERATOR_NEQ, $wrongAge, true];
        yield 'not equal / no match' => ['1991/10/16', Rule::OPERATOR_NEQ, $correctAge, false];
        yield 'not equal / fallback match' => [null, Rule::OPERATOR_NEQ, $correctAge, true];
    }
}

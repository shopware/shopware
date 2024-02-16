<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerTagRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerTagRule::class)]
#[Group('rules')]
class CustomerTagRuleTest extends TestCase
{
    private CustomerTagRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CustomerTagRule();
    }

    public function testRuleConfig(): void
    {
        $expectedConfiguration = [
            'operatorSet' => [
                'operators' => [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_NEQ,
                    Rule::OPERATOR_EMPTY,
                ],
                'isMatchAny' => 1,
            ],
            'fields' => [
                'identifiers' => [
                    'name' => 'identifiers',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => 'tag',
                    ],
                ],
            ],
        ];

        $data = $this->rule->getConfig()->getData();
        static::assertEquals($expectedConfiguration, $data);
    }

    public function testConstraints(): void
    {
        $operators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('identifiers', $constraints, 'identifiers constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals(new ArrayOfUuid(), $constraints['identifiers'][1]);
        static::assertEquals(new Choice($operators), $constraints['operator'][1]);
    }

    /**
     * @param string|list<string>|null $givenIdentifier
     * @param array<string> $ruleIdentifiers
     */
    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, array $ruleIdentifiers, array|string|null $givenIdentifier, bool $noCustomer = false): void
    {
        $customer = new CustomerEntity();

        /** @var list<string> $customerIdentifiers */
        $customerIdentifiers = array_filter(\is_array($givenIdentifier) ? $givenIdentifier : [$givenIdentifier]);
        $customer->setTagIds($customerIdentifiers);

        if ($noCustomer) {
            $customer = null;
        }

        $scope = $this->createScope($customer);
        $this->rule->assign(['identifiers' => $ruleIdentifiers, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'operator_eq / not match / identifier' => [Rule::OPERATOR_EQ, false, ['kyln123', 'kyln456'], 'kyln000'];
        yield 'operator_eq / match partly / identifier' => [Rule::OPERATOR_EQ, true, ['kyln123', 'kyln456'], 'kyln123'];
        yield 'operator_eq / match full / identifier' => [Rule::OPERATOR_EQ, true, ['kyln123', 'kyln456'], ['kyln123', 'kyln456']];
        yield 'operator_eq / no match / no customer' => [Rule::OPERATOR_EQ, false, ['kyln123', 'kyln456'], 'kyln123', true];
        yield 'operator_neq / match / identifier' => [Rule::OPERATOR_NEQ, true, ['kyln123', 'kyln456'], 'kyln000'];
        yield 'operator_neq / not match / identifier' => [Rule::OPERATOR_NEQ, false, ['kyln123', 'kyln456'], 'kyln123'];
        yield 'operator_empty / not match / identifier' => [Rule::OPERATOR_NEQ, false, ['kyln123', 'kyln456'], 'kyln123'];
        yield 'operator_empty / match / identifier' => [Rule::OPERATOR_EMPTY, true, ['kyln123', 'kyln456'], null];
        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, true, ['kyln123', 'kyln456'], 'kyln123', true];
        yield 'operator_empty / match / no customer' => [Rule::OPERATOR_EMPTY, true, ['kyln123', 'kyln456'], 'kyln123', true];
    }

    public function createScope(?CustomerEntity $customer): CheckoutRuleScope
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        return new CheckoutRuleScope($context);
    }
}

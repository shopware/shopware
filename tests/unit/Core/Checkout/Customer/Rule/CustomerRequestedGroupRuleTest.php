<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerRequestedGroupRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CustomerRequestedGroupRule::class)]
#[Group('rules')]
class CustomerRequestedGroupRuleTest extends TestCase
{
    private CustomerRequestedGroupRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CustomerRequestedGroupRule();
    }

    public function testName(): void
    {
        static::assertSame('customerRequestedGroup', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');
        static::assertArrayHasKey('customerGroupIds', $constraints, 'Constraint customerGroupIds not found in Rule');
        static::assertEquals(RuleConstraints::uuidOperators(), $constraints['operator']);
        static::assertEquals(RuleConstraints::uuids(), $constraints['customerGroupIds']);
    }

    public function testGetConstraintsOperatorEmpty(): void
    {
        $rule = new CustomerRequestedGroupRule(Rule::OPERATOR_EMPTY);
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');
        static::assertArrayNotHasKey('customerGroupIds', $constraints, 'Constraint customerGroupIds found in Rule');
        static::assertEquals(RuleConstraints::uuidOperators(), $constraints['operator']);
    }

    public function testGetConfig(): void
    {
        $config = $this->rule->getConfig();
        static::assertEquals([
            'operatorSet' => [
                'operators' => [
                    ...RuleConfig::OPERATOR_SET_STRING,
                    Rule::OPERATOR_EMPTY,
                ],
                'isMatchAny' => true,
            ],
            'fields' => [
                'customerGroupIds' => [
                    'name' => 'customerGroupIds',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => CustomerGroupDefinition::ENTITY_NAME,
                    ],
                ],
            ],
        ], $config->getData());
    }

    /**
     * @param array<string> $customerGroupIds
     */
    #[DataProvider('getMatchValues')]
    public function testCustomerRequestedGroupRuleMatching(bool $expected, bool $loggedIn, ?string $requestedGroupId, array $customerGroupIds, string $operator): void
    {
        $customer = null;

        if ($loggedIn) {
            $customer = new CustomerEntity();

            if ($requestedGroupId !== null) {
                $customerGroup = new CustomerGroupEntity();
                $customerGroup->setId($requestedGroupId);
                $customer->setRequestedGroup($customerGroup);
                $customer->setRequestedGroupId($requestedGroupId);
            }
        }

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['customerGroupIds' => $customerGroupIds, 'operator' => $operator]);

        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);
        $this->rule->assign(['customerGroupIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return \Traversable<string, array{expected: bool, loggedIn: bool, requestedGroupId: string|null, customerGroupIds: array<string>, operator: string}>
     */
    public static function getMatchValues(): \Traversable
    {
        $id = Uuid::randomHex();

        yield 'operator_one_of / no match' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'customerGroupIds' => [Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_one_of / one match' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'customerGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_one_of / empty' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => null,
            'customerGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_one_of / not logged in' => [
            'expected' => false,
            'loggedIn' => false,
            'requestedGroupId' => null,
            'customerGroupIds' => [$id],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_none_of / no match' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'customerGroupIds' => [Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_none_of / one match' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'customerGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_none_of / empty' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => null,
            'customerGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_none_of / not logged in' => [
            'expected' => true,
            'loggedIn' => false,
            'requestedGroupId' => null,
            'customerGroupIds' => [$id],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_empty / empty' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => null,
            'customerGroupIds' => [],
            'operator' => Rule::OPERATOR_EMPTY,
        ];

        yield 'operator_empty / not empty' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'customerGroupIds' => [],
            'operator' => Rule::OPERATOR_EMPTY,
        ];

        yield 'operator_empty / not logged in' => [
            'expected' => true,
            'loggedIn' => false,
            'requestedGroupId' => null,
            'customerGroupIds' => [],
            'operator' => Rule::OPERATOR_EMPTY,
        ];
    }
}

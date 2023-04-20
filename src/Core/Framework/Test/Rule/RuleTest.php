<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * @internal
 */
#[Package('business-ops')]
class RuleTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CartRuleHelperTrait;

    private EntityRepository $conditionRepository;

    private RuleConditionRegistry $conditionRegistry;

    private Context $context;

    protected function setUp(): void
    {
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->conditionRegistry = $this->getContainer()->get(RuleConditionRegistry::class);
        $this->context = Context::createDefaultContext();
        $this->context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
    }

    public function testScope(): CartRuleScope
    {
        $lineItemCollection = new LineItemCollection([$this->createLineItem()]);
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [SalesChannelContextService::CUSTOMER_ID => $this->createCustomer()]
            );

        return new CartRuleScope($this->createCart($lineItemCollection), $context);
    }

    /**
     * @depends testScope
     */
    public function testRulesMatchWithEmptyOperator(CartRuleScope $scope): void
    {
        /** @var Rule $rule */
        foreach ($this->getRulesWithEmptyOperator() as $rule) {
            $rule->assign(['operator' => Rule::OPERATOR_EMPTY]);

            $lineItem = $scope->getCart()->getLineItems()->first();
            static::assertNotNull($lineItem);
            $lineItemScope = new LineItemScope(
                $lineItem,
                $scope->getSalesChannelContext()
            );

            try {
                $rule->match($scope);
                $rule->match($lineItemScope);
            } catch (\Throwable $exception) {
                static::fail(sprintf(
                    'Condition %s threw exception matching with empty operator and no other assigned values: %s',
                    $rule->getName(),
                    $exception->getMessage()
                ));
            }
        }
    }

    public function testRulesPersistWithEmptyOperator(): void
    {
        /** @var Rule $rule */
        foreach ($this->getRulesWithEmptyOperator() as $rule) {
            try {
                $this->conditionRepository->create([
                    [
                        'type' => $rule->getName(),
                        'rule' => ['name' => 'test', 'priority' => 1],
                        'value' => ['operator' => Rule::OPERATOR_EMPTY],
                    ],
                ], $this->context);
            } catch (\Throwable $exception) {
                static::fail(sprintf(
                    'Threw exception persisting condition %s with empty operator and no other assigned values: %s',
                    $rule->getName(),
                    $exception->getMessage()
                ));
            }
        }
    }

    public function testConfigOperatorsMatchConstraints(): void
    {
        /** @var Rule $rule */
        foreach ($this->getRules() as $rule) {
            try {
                $constraints = $rule->getConstraints();
                $config = $rule->getConfig();
            } catch (\Throwable) {
                continue;
            }

            if ($config === null) {
                continue;
            }

            $configData = $config->getData();
            $configOperators = $configData['operatorSet']['operators'] ?? null;

            if (empty($constraints['operator']) && empty($configOperators)) {
                continue;
            }

            if (empty($constraints['operator']) && !empty($configOperators)) {
                static::fail(sprintf(
                    'Missing constraints in condition %s for operator while config has operator set',
                    $rule->getName()
                ));
            }

            if (!empty($constraints['operator']) && empty($configOperators)) {
                static::fail(sprintf(
                    'Missing operator set for config of condition %s while constraints require operator',
                    $rule->getName()
                ));
            }

            $choiceConstraint = current(array_filter($constraints['operator'], fn (Constraint $operatorConstraints) => $operatorConstraints instanceof Choice));

            if (!$choiceConstraint) {
                continue;
            }

            static::assertEmpty(array_diff($choiceConstraint->choices, $configOperators), sprintf(
                'Constraints and config for operator differ in condition %s',
                $rule->getName()
            ));
        }
    }

    public function testRuleNameConfigByConstant(): void
    {
        /** @var Rule $rule */
        foreach ($this->getRules() as $rule) {
            $ruleNameConstant = $rule::RULE_NAME; /* @phpstan-ignore-line */

            static::assertNotNull($ruleNameConstant, sprintf(
                'Rule name constant is empty in condition %s',
                $rule->getName()
            ));
        }
    }

    /**
     * @return \ArrayIterator<int, Rule>
     */
    private function getRulesWithEmptyOperator(): \Traversable
    {
        /** @var Rule $rule */
        foreach ($this->getRules() as $rule) {
            try {
                $constraints = $rule->getConstraints();
            } catch (\Throwable) {
                continue;
            }

            // skip instances of Rule that don't have an operator constraint
            if (empty($constraints['operator'])) {
                continue;
            }

            $choiceConstraint = current(array_filter($constraints['operator'], fn (Constraint $operatorConstraints) => $operatorConstraints instanceof Choice));

            if (!$choiceConstraint) {
                continue;
            }

            // skip if rule does not allow empty operator
            if (!\in_array(Rule::OPERATOR_EMPTY, $choiceConstraint->choices, true)) {
                continue;
            }

            yield $rule;
        }
    }

    /**
     * @return \ArrayIterator<int, Rule>
     */
    private function getRules(): \Traversable
    {
        $ruleNames = $this->conditionRegistry->getNames();

        foreach ($ruleNames as $ruleName) {
            yield $this->conditionRegistry->getRuleInstance($ruleName);
        }
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'foo@bar.de',
                'password' => 'password',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, $this->context);

        return $customerId;
    }
}

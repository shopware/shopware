<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Faker\Generator;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopware\Core\Checkout\Customer\Rule\DaysSinceFirstLoginRule;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
class RuleGenerator implements DemodataGeneratorInterface
{
    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $ruleRepository,
        private readonly EntityWriterInterface $writer,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly RuleDefinition $ruleDefinition
    ) {
    }

    public function getDefinition(): string
    {
        return RuleDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();

        /** @var list<string> $paymentMethodIds */
        $paymentMethodIds = $this->paymentMethodRepository->searchIds(new Criteria(), $context->getContext())->getIds();
        /** @var list<string> $shippingMethodIds */
        $shippingMethodIds = $this->shippingMethodRepository->searchIds(new Criteria(), $context->getContext())->getIds();

        $criteria = (new Criteria())->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsAnyFilter('rule.shippingMethods.id', $shippingMethodIds),
                    new EqualsAnyFilter('rule.paymentMethods.id', $paymentMethodIds),
                ]
            )
        );

        $ids = $this->ruleRepository->searchIds($criteria, $context->getContext());

        if (!empty($ids->getIds())) {
            return;
        }

        $pool = [
            [
                'rule' => (new DaysSinceFirstLoginRule())->assign(['daysPassed' => 0]),
                'name' => 'New customer',
            ],
            [
                'rule' => (new DateRangeRule())->assign(['fromDate' => new \DateTime(), 'toDate' => (new \DateTime())->modify('+2 day')]),
                'name' => 'Next two days',
            ],
            [
                'rule' => (new GoodsPriceRule())->assign(['amount' => 5000, 'operator' => GoodsPriceRule::OPERATOR_GTE]),
                'name' => 'Cart >= 5000',
            ],
            [
                'rule' => (new CustomerGroupRule())->assign(['customerGroupIds' => [TestDefaults::FALLBACK_CUSTOMER_GROUP]]),
                'name' => 'Default group',
            ],
            [
                'rule' => (new CurrencyRule())->assign(['currencyIds' => [Defaults::CURRENCY]]),
                'name' => 'Default currency',
            ],
        ];

        $payload = [];
        for ($i = 0; $i < 20; ++$i) {
            $rules = \array_slice($pool, random_int(0, \count($pool) - 2), random_int(1, 2));

            $classes = array_column($rules, 'rule');
            $names = array_column($rules, 'name');

            $ruleData = [
                'id' => Uuid::randomHex(),
                'priority' => $i,
                'name' => implode(' + ', $names),
                'description' => $context->getFaker()->text(),
            ];

            $ruleData['conditions'][] = $this->buildChildRule(null, (new OrRule())->assign(['rules' => $classes]));

            $payload[] = $ruleData;
        }

        // nested condition
        $nestedRule = new OrRule();

        $nestedRuleData = [
            'id' => Uuid::randomHex(),
            'priority' => 20,
            'name' => 'nested rule',
            'description' => $context->getFaker()->text(),
        ];

        $this->buildNestedRule($nestedRule, $pool, 0, 6);

        $nestedRuleData['conditions'][] = $this->buildChildRule(null, $nestedRule);

        $payload[] = $nestedRuleData;

        $writeContext = WriteContext::createFromContext($context->getContext());

        $this->writer->insert($this->ruleDefinition, $payload, $writeContext);
    }

    /**
     * @param list<array{rule: Rule, name: string}> $pool
     */
    private function buildNestedRule(Rule $rule, array $pool, int $currentDepth, int $depth): Rule
    {
        if ($currentDepth === $depth) {
            return $rule;
        }

        $rules = $this->faker->randomElements($pool, 2);

        $classes = array_column($rules, 'rule');

        if ($currentDepth % 2 === 1) {
            $classes[] = $this->buildNestedRule(new OrRule(), $pool, $currentDepth + 1, $depth);
        } else {
            $classes[] = $this->buildNestedRule(new AndRule(), $pool, $currentDepth + 1, $depth);
        }

        $rule->assign(['rules' => $classes]);

        return $rule;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChildRule(?string $parentId, Rule $rule): array
    {
        $data = [];
        $data['value'] = $rule->jsonSerialize();
        unset($data['value']['_class'], $data['value']['rules'], $data['value']['extensions']);

        if ($rule instanceof FilterRule) {
            unset($data['value']['filter']);
        }

        if (!$data['value']) {
            unset($data['value']);
        }
        $data['id'] = Uuid::randomHex();
        $data['parentId'] = $parentId;
        $data['type'] = $rule->getName();

        if ($rule instanceof Container) {
            $data['children'] = [];
            foreach ($rule->getRules() as $index => $childRule) {
                $child = $this->buildChildRule($data['id'], $childRule);
                $child['position'] = $index;
                $data['children'][] = $child;
            }
        }

        return $data;
    }
}

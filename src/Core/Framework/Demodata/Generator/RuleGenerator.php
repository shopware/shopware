<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopware\Core\Checkout\Customer\Rule\IsNewCustomerRule;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\Rule\CurrencyRule;

class RuleGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodRepository;

    /**
     * @var RuleDefinition
     */
    private $ruleDefinition;

    public function __construct(
        EntityRepositoryInterface $ruleRepository,
        EntityWriterInterface $writer,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        RuleDefinition $ruleDefinition
    ) {
        $this->ruleRepository = $ruleRepository;
        $this->writer = $writer;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->ruleDefinition = $ruleDefinition;
    }

    public function getDefinition(): string
    {
        return RuleDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $paymentMethodIds = $this->paymentMethodRepository->searchIds(new Criteria(), $context->getContext());
        $shippingMethodIds = $this->shippingMethodRepository->searchIds(new Criteria(), $context->getContext());

        $criteria = (new Criteria())->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsAnyFilter('rule.shippingMethods.id', $shippingMethodIds->getIds()),
                    new EqualsAnyFilter('rule.paymentMethods.id', $paymentMethodIds->getIds()),
                ]
            )
        );

        $ids = $this->ruleRepository->searchIds($criteria, $context->getContext());

        if (!empty($ids->getIds())) {
            return;
        }

        $pool = [
            [
                'rule' => new IsNewCustomerRule(),
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
                'rule' => (new CustomerGroupRule())->assign(['customerGroupIds' => [Defaults::FALLBACK_CUSTOMER_GROUP]]),
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

    private function buildNestedRule(Rule $rule, array $pool, int $currentDepth, int $depth): Rule
    {
        if ($currentDepth === $depth) {
            return $rule;
        }

        $rules = \array_slice($pool, random_int(0, \count($pool) - 2), random_int(1, 2));

        $classes = array_column($rules, 'rule');

        if ($currentDepth % 2 === 1) {
            $classes[] = $this->buildNestedRule(new OrRule(), $pool, $currentDepth + 1, $depth);
        } else {
            $classes[] = $this->buildNestedRule(new AndRule(), $pool, $currentDepth + 1, $depth);
        }

        $rule->assign(['rules' => $classes]);

        return $rule;
    }

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

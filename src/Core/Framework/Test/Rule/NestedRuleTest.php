<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerLoggedInRule;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\NestedRule;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class NestedRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepositoryInterface $ruleRepository;

    private EntityRepositoryInterface $conditionRepository;

    private Context $context;

    private IdsCollection $idCollection;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->idCollection = new IdsCollection();
    }

    public function testIfRuleIsConsistent(): void
    {
        $this->ruleRepository->create(
            [
                ['id' => $this->idCollection->get('basic-rule'), 'name' => 'Demo rule', 'priority' => 1],
                ['id' => $this->idCollection->get('nested-rule'), 'name' => 'Nested demo rule', 'priority' => 1],
            ],
            Context::createDefaultContext()
        );

        $this->conditionRepository->create([
            [
                'id' => $this->idCollection->get('basic-condition'),
                'type' => (new AlwaysValidRule())->getName(),
                'ruleId' => $this->idCollection->get('basic-rule'),
            ],
            [
                'id' => $this->idCollection->get('nested-condition'),
                'type' => (new NestedRule())->getName(),
                'ruleId' => $this->idCollection->get('nested-rule'),
                'value' => [
                    'operator' => NestedRule::OPERATOR_EQ,
                    'ruleId' => $this->idCollection->get('basic-rule'),
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$this->idCollection->get('nested-condition')]), $this->context)->get($this->idCollection->get('nested-condition')));
        /** @var RuleEntity $ruleStruct */
        $ruleStruct = $this->ruleRepository->search(new Criteria([$this->idCollection->get('nested-rule')]), $this->context)->get($this->idCollection->get('nested-rule'));
        static::assertEquals(new AndRule([new NestedRule(NestedRule::OPERATOR_EQ, $this->idCollection->get('basic-rule'), (new AndRule([new AlwaysValidRule()])))]), $ruleStruct->getPayload());
    }

    public function testIfMultiNestedRuleIsConsistent(): void
    {
        $this->ruleRepository->create(
            [
                ['id' => $this->idCollection->get('basic-rule'), 'name' => 'Demo rule', 'priority' => 1],
                ['id' => $this->idCollection->get('nested-rule'), 'name' => 'Nested demo rule', 'priority' => 1],
                ['id' => $this->idCollection->get('multi-nested-rule'), 'name' => 'Multi Nested demo rule', 'priority' => 1],
            ],
            Context::createDefaultContext()
        );

        $this->conditionRepository->create([
            [
                'id' => $this->idCollection->get('basic-condition'),
                'type' => (new AlwaysValidRule())->getName(),
                'ruleId' => $this->idCollection->get('basic-rule'),
            ],
            [
                'id' => $this->idCollection->get('nested-condition'),
                'type' => (new NestedRule())->getName(),
                'ruleId' => $this->idCollection->get('nested-rule'),
                'value' => [
                    'operator' => NestedRule::OPERATOR_EQ,
                    'ruleId' => $this->idCollection->get('basic-rule'),
                ],
            ],
            [
                'id' => $this->idCollection->get('multi-nested-condition'),
                'type' => (new NestedRule())->getName(),
                'ruleId' => $this->idCollection->get('multi-nested-rule'),
                'value' => [
                    'operator' => NestedRule::OPERATOR_EQ,
                    'ruleId' => $this->idCollection->get('nested-rule'),
                ],
            ],
        ], $this->context);

        /** @var RuleEntity $ruleStruct */
        $ruleStruct = $this->ruleRepository->search(new Criteria([$this->idCollection->get('nested-rule')]), $this->context)->get($this->idCollection->get('nested-rule'));
        static::assertEquals(new AndRule([new NestedRule(NestedRule::OPERATOR_EQ, $this->idCollection->get('basic-rule'), (new AndRule([new AlwaysValidRule()])))]), $ruleStruct->getPayload());

        /** @var RuleEntity $nestedRuleStruct */
        $nestedRuleStruct = $this->ruleRepository->search(new Criteria([$this->idCollection->get('multi-nested-rule')]), $this->context)->get($this->idCollection->get('multi-nested-rule'));
        static::assertEquals(
            new AndRule([
                new NestedRule(
                    NestedRule::OPERATOR_EQ,
                    $this->idCollection->get('nested-rule'),
                    (new AndRule([
                        new NestedRule(
                            NestedRule::OPERATOR_EQ,
                            $this->idCollection->get('basic-rule'),
                            (new AndRule([
                                new AlwaysValidRule(),
                            ]))
                        ),
                    ]))
                ),
            ]),
            $nestedRuleStruct->getPayload()
        );
    }

    public function testRuleUpdateIsConsistent(): void
    {
        $this->ruleRepository->create(
            [
                ['id' => $this->idCollection->get('basic-rule'), 'name' => 'Demo rule', 'priority' => 1],
                ['id' => $this->idCollection->get('nested-rule'), 'name' => 'Nested demo rule', 'priority' => 1],
            ],
            Context::createDefaultContext()
        );

        $this->conditionRepository->create([
            [
                'id' => $this->idCollection->get('basic-condition'),
                'type' => (new AlwaysValidRule())->getName(),
                'ruleId' => $this->idCollection->get('basic-rule'),
            ],
            [
                'id' => $this->idCollection->get('nested-condition'),
                'type' => (new NestedRule())->getName(),
                'ruleId' => $this->idCollection->get('nested-rule'),
                'value' => [
                    'operator' => NestedRule::OPERATOR_EQ,
                    'ruleId' => $this->idCollection->get('basic-rule'),
                ],
            ],
        ], $this->context);

        /** @var RuleEntity $ruleStruct */
        $ruleStruct = $this->ruleRepository->search(new Criteria([$this->idCollection->get('nested-rule')]), $this->context)->get($this->idCollection->get('nested-rule'));
        static::assertEquals(new AndRule([new NestedRule(NestedRule::OPERATOR_EQ, $this->idCollection->get('basic-rule'), (new AndRule([new AlwaysValidRule()])))]), $ruleStruct->getPayload());

        $this->conditionRepository->update([
            [
                'id' => $this->idCollection->get('basic-condition'),
                'type' => (new CustomerLoggedInRule())->getName(),
                'value' => [
                    'isLoggedIn' => false,
                ],
            ],
        ], $this->context);

        $ruleStruct = $this->ruleRepository->search(new Criteria([$this->idCollection->get('nested-rule')]), $this->context)->get($this->idCollection->get('nested-rule'));
        static::assertEquals(new AndRule([new NestedRule(NestedRule::OPERATOR_EQ, $this->idCollection->get('basic-rule'), (new AndRule([new CustomerLoggedInRule()])))]), $ruleStruct->getPayload());
    }

    public function testNestedRuleUpdateIsConsistent(): void
    {
        $this->ruleRepository->create(
            [
                ['id' => $this->idCollection->get('basic-rule'), 'name' => 'Demo rule', 'priority' => 1],
                ['id' => $this->idCollection->get('other-basic-rule'), 'name' => 'Second demo rule', 'priority' => 1],
                ['id' => $this->idCollection->get('nested-rule'), 'name' => 'Nested demo rule', 'priority' => 1],
            ],
            Context::createDefaultContext()
        );

        $this->conditionRepository->create([
            [
                'id' => $this->idCollection->get('basic-condition'),
                'type' => (new AlwaysValidRule())->getName(),
                'ruleId' => $this->idCollection->get('basic-rule'),
            ],
            [
                'id' => $this->idCollection->get('other-basic-condition'),
                'type' => (new CustomerLoggedInRule())->getName(),
                'ruleId' => $this->idCollection->get('other-basic-rule'),
                'value' => [
                    'isLoggedIn' => false,
                ],
            ],
            [
                'id' => $this->idCollection->get('nested-condition'),
                'type' => (new NestedRule())->getName(),
                'ruleId' => $this->idCollection->get('nested-rule'),
                'value' => [
                    'operator' => NestedRule::OPERATOR_EQ,
                    'ruleId' => $this->idCollection->get('basic-rule'),
                ],
            ],
        ], $this->context);

        /** @var RuleEntity $ruleStruct */
        $ruleStruct = $this->ruleRepository->search(new Criteria([$this->idCollection->get('nested-rule')]), $this->context)->get($this->idCollection->get('nested-rule'));
        static::assertEquals(new AndRule([new NestedRule(NestedRule::OPERATOR_EQ, $this->idCollection->get('basic-rule'), (new AndRule([new AlwaysValidRule()])))]), $ruleStruct->getPayload());

        $this->conditionRepository->update([
            [
                'id' => $this->idCollection->get('nested-condition'),
                'value' => [
                    'operator' => NestedRule::OPERATOR_EQ,
                    'ruleId' => $this->idCollection->get('other-basic-rule'),
                ],
            ],
        ], $this->context);

        $ruleStruct = $this->ruleRepository->search(new Criteria([$this->idCollection->get('nested-rule')]), $this->context)->get($this->idCollection->get('nested-rule'));
        static::assertEquals(new AndRule([new NestedRule(NestedRule::OPERATOR_EQ, $this->idCollection->get('other-basic-rule'), (new AndRule([new CustomerLoggedInRule()])))]), $ruleStruct->getPayload());
    }
}

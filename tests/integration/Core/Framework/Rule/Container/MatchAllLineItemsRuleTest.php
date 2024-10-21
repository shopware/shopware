<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Rule\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
class MatchAllLineItemsRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithInvalidRulesType(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new MatchAllLineItemsRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'rules' => ['Rule'],
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/rules', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithValidRulesType(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new MatchAllLineItemsRule())->getName(),
                'ruleId' => $ruleId,
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }

    public function testValidateWithValidRulesTypeWithChildren(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        $id = Uuid::randomHex();
        $categoryIds = [Uuid::randomHex()];
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new MatchAllLineItemsRule())->getName(),
                'ruleId' => $ruleId,
                'children' => [
                    [
                        'type' => (new LineItemInCategoryRule())->getName(),
                        'ruleId' => $ruleId,
                        'value' => [
                            'operator' => MatchAllLineItemsRule::OPERATOR_EQ,
                            'categoryIds' => $categoryIds,
                        ],
                    ],
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
        $ruleStruct = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->getEntities()->get($ruleId);
        static::assertInstanceOf(RuleEntity::class, $ruleStruct);
        static::assertEquals(new AndRule([new MatchAllLineItemsRule([new LineItemInCategoryRule(MatchAllLineItemsRule::OPERATOR_EQ, $categoryIds)])]), $ruleStruct->getPayload());

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }
}

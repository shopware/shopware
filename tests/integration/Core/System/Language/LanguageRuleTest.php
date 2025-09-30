<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Language;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\Rule\LanguageRule;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class LanguageRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingParameters(): void
    {
        try {
            $this->createCondition();
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/languageIds', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithEmptyLanguageIds(): void
    {
        try {
            $this->createCondition(['operator' => Rule::OPERATOR_EQ, 'languageIds' => []]);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/languageIds', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidLanguageIdsUuid(): void
    {
        try {
            /** @phpstan-ignore argument.type (for test purpose) */
            $this->createCondition(['operator' => Rule::OPERATOR_EQ, 'languageIds' => ['INVALID-UUID', true, 3]]);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);
            static::assertSame('/0/value/languageIds', $exceptions[0]['source']['pointer']);
            static::assertSame('/0/value/languageIds', $exceptions[1]['source']['pointer']);
            static::assertSame('/0/value/languageIds', $exceptions[2]['source']['pointer']);

            static::assertSame('The value "INVALID-UUID" is not a valid uuid.', $exceptions[0]['detail']);
            static::assertSame('The value "1" is not a valid uuid.', $exceptions[1]['detail']);
            static::assertSame('The value "3" is not a valid uuid.', $exceptions[2]['detail']);
        }
    }

    public function testValidateWithValidOperators(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $conditionIdEq = Uuid::randomHex();
        $conditionIdNEq = Uuid::randomHex();

        $this->createCondition(
            ['operator' => Rule::OPERATOR_EQ, 'languageIds' => [Uuid::randomHex(), Uuid::randomHex()]],
            $conditionIdEq,
            $ruleId
        );
        $this->createCondition(
            ['operator' => Rule::OPERATOR_NEQ, 'languageIds' => [Uuid::randomHex(), Uuid::randomHex()]],
            $conditionIdNEq,
            $ruleId
        );

        static::assertCount(
            2,
            $this->conditionRepository->search(
                new Criteria([$conditionIdEq, $conditionIdNEq]),
                $this->context
            )
        );
    }

    public function testValidateWithInvalidOperators(): void
    {
        foreach ([Rule::OPERATOR_LTE, Rule::OPERATOR_GTE, 'Invalid'] as $operator) {
            try {
                $this->createCondition(['operator' => $operator, 'languageIds' => [Uuid::randomHex(), Uuid::randomHex()]]);
                static::fail('Exception was not thrown');
            } catch (WriteException $stackException) {
                $exceptions = iterator_to_array($stackException->getErrors());
                static::assertCount(1, $exceptions);
                static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
                static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $exceptions[0]['code']);
            }
        }
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $this->createCondition(
            ['operator' => Rule::OPERATOR_EQ, 'languageIds' => [Uuid::randomHex(), Uuid::randomHex()]],
            $id,
            $ruleId
        );

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    /**
     * @param array{operator: string, languageIds: list<string>}|null $value
     */
    private function createCondition(?array $value = null, ?string $id = null, ?string $ruleId = null): void
    {
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new LanguageRule())->getName(),
                'ruleId' => $ruleId ?? Uuid::randomHex(),
                'value' => $value,
            ],
        ], $this->context);
    }
}

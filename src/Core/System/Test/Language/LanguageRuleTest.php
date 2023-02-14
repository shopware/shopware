<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Language;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\Language\Rule\LanguageRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('business-ops')]
class LanguageRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private Context $context;

    private LanguageRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->rule = new LanguageRule();
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

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertArrayHasKey('languageIds', $ruleConstraints, 'Constraint languageIds not found in Rule');
        $languageIds = $ruleConstraints['languageIds'];
        static::assertEquals(new NotBlank(), $languageIds[0]);
        static::assertEquals(new ArrayOfUuid(), $languageIds[1]);
    }

    /**
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(string $operator, bool $isMatching, string $languageId): void
    {
        $languageIds = ['kyln123', 'kyln456'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);

        $salesChannelContext->method('getContext')->willReturn($context);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['languageIds' => $languageIds, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public static function getMatchValues(): array
    {
        return [
            'operator_eq / not match / language id' => [Rule::OPERATOR_EQ, false, Uuid::randomHex()],
            'operator_eq / match / language id' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / language id' => [Rule::OPERATOR_NEQ, true,  Uuid::randomHex()],
            'operator_neq / not match / language id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
        ];
    }

    public function testCallingMatchWithoutValueThrowsException(): void
    {
        try {
            $salesChannelContext = $this->createMock(SalesChannelContext::class);
            $scope = new CheckoutRuleScope($salesChannelContext);
            $value = null;
            $rule = new LanguageRule(Rule::OPERATOR_EQ, $value);
            $rule->match($scope);
            static::fail('Exception was not thrown');
        } catch (UnsupportedValueException $exception) {
            static::assertEquals(
                sprintf('Unsupported value of type %s in %s', \gettype($value), LanguageRule::class),
                $exception->getMessage()
            );
        }
    }

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

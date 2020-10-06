<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $conditionRepository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingIdentifiers(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = \iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/identifiers', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithEmptyIdentifiers(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => [],
                        'operator' => LineItemRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = \iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/identifiers', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithStringIdentifiers(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => '0915d54fbf80423c917c61ad5a391b48',
                        'operator' => LineItemRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = \iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/identifiers', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidArrayIdentifiers(): void
    {
        $conditionId = Uuid::randomHex();

        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'identifiers' => [true, 3, '1234abcd', '0915d54fbf80423c917c61ad5a391b48'],
                        'operator' => LineItemRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = \iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);

            static::assertSame('/0/value/identifiers', $exceptions[0]['source']['pointer']);
            static::assertSame('/0/value/identifiers', $exceptions[1]['source']['pointer']);
            static::assertSame('/0/value/identifiers', $exceptions[2]['source']['pointer']);

            static::assertSame(ArrayOfUuid::INVALID_TYPE_CODE, $exceptions[0]['code']);
            static::assertSame(ArrayOfUuid::INVALID_TYPE_CODE, $exceptions[1]['code']);
            static::assertSame(ArrayOfUuid::INVALID_TYPE_CODE, $exceptions[2]['code']);
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
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new LineItemRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'identifiers' => ['0915d54fbf80423c917c61ad5a391b48', '6f7a6b89579149b5b687853271608949'],
                    'operator' => LineItemRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testNotMatchesWithoutId(): void
    {
        $rule = new LineItemRule(LineItemRule::OPERATOR_EQ, ['A', 'B']);

        $lineItem = new LineItem('A', 'test');

        $matches = $rule->match(new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class)));

        static::assertFalse($matches);
    }

    public function testMatchesWithreferencedId(): void
    {
        $rule = new LineItemRule(LineItemRule::OPERATOR_EQ, ['A', 'B']);

        $lineItem = new LineItem('A', 'test', 'A');

        $matches = $rule->match(new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class)));

        static::assertTrue($matches);
    }

    public function testNotMatchesWithPayloadId(): void
    {
        $rule = new LineItemRule(LineItemRule::OPERATOR_NEQ, ['A', 'B']);

        $lineItem = (new LineItem('A', 'test'))->setPayloadValue('id', 'A');

        $matches = $rule->match(new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class)));

        static::assertFalse($matches);
    }

    public function testNotMatchesDifferentPayloadId(): void
    {
        $rule = new LineItemRule(LineItemRule::OPERATOR_EQ, ['A', 'B']);

        $lineItem = (new LineItem('C', 'test'))->setPayloadValue('id', 'C');

        $matches = $rule->match(new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class)));

        static::assertFalse($matches);
    }
}

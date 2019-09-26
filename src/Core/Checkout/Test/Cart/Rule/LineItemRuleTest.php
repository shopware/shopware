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
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;

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
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(2, $exception->getViolations());
                static::assertSame('/0/value/identifiers', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());

                static::assertSame('/0/value/operator', $exception->getViolations()->get(1)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(1)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(1)->getMessage());
            }
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
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/identifiers', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
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
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/identifiers', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should be of type array.', $exception->getViolations()->get(0)->getMessage());
            }
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
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(3, $exception->getViolations());
                static::assertSame('/0/value/identifiers', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('The value "1" is not a valid uuid.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('The value "3" is not a valid uuid.', $exception->getViolations()->get(1)->getMessage());
                static::assertSame('The value "1234abcd" is not a valid uuid.', $exception->getViolations()->get(2)->getMessage());
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

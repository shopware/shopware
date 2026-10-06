<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Order\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Rule\DaysSinceOrderPlacedRule;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class DaysSinceOrderPlacedRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
    }

    public function testSavedRulePayloadMatchesTheCalendarDayWindow(): void
    {
        $ruleId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->ruleRepository->create([[
            'id' => $ruleId,
            'name' => 'Order placed within 30 days',
            'priority' => 100,
            'conditions' => [[
                'type' => OrRule::RULE_NAME,
                'children' => [[
                    'type' => AndRule::RULE_NAME,
                    'children' => [[
                        'type' => DaysSinceOrderPlacedRule::RULE_NAME,
                        'value' => ['operator' => Rule::OPERATOR_LTE, 'daysPassed' => 30],
                    ]],
                ]],
            ]],
        ]], $context);

        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $context)->getEntities()->get($ruleId);
        static::assertNotNull($rule);
        static::assertFalse($rule->isInvalid());
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);

        $order = static::createStub(OrderEntity::class);
        $order->method('getOrderDate')->willReturn(new \DateTimeImmutable('2024-01-01T00:00:00+00:00'));
        $scope = static::createStub(FlowRuleScope::class);
        $scope->method('getOrder')->willReturn($order);
        $scope->method('getCurrentTime')->willReturnOnConsecutiveCalls(
            new \DateTimeImmutable('2024-01-31T23:59:00+00:00'),
            new \DateTimeImmutable('2024-02-01T00:00:00+00:00')
        );

        static::assertTrue($payload->match($scope));
        static::assertFalse($payload->match($scope));
    }

    /**
     * @return iterable<string, array{float|string|null}>
     */
    public static function invalidDayCounts(): iterable
    {
        yield 'missing required value' => [null];
        yield 'fractional day count' => [30.5];
        yield 'numeric string' => ['30'];
    }

    #[DataProvider('invalidDayCounts')]
    public function testRejectsMissingOrNonIntegerDayCounts(float|string|null $daysPassed): void
    {
        try {
            $this->ruleRepository->create([[
                'name' => 'Invalid day count',
                'priority' => 100,
                'conditions' => [[
                    'type' => DaysSinceOrderPlacedRule::RULE_NAME,
                    'value' => ['operator' => Rule::OPERATOR_LTE, 'daysPassed' => $daysPassed],
                ]],
            ]], Context::createDefaultContext());
        } catch (WriteException $exception) {
            $errors = iterator_to_array($exception->getErrors());
            static::assertNotEmpty($errors);
            static::assertSame('/0/conditions/0/value/daysPassed', $errors[0]['source']['pointer']);

            return;
        }

        static::fail('The rule write must reject a missing or non-integer day count.');
    }
}

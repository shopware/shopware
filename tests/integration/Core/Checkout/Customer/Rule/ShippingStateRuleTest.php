<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Rule\ShippingStateRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(ShippingStateRule::class)]
#[Group('rules')]
class ShippingStateRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidationWithMissingStateIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingStateRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/stateIds', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidationWithEmptyStateIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingStateRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'stateIds' => [],
                        'operator' => ShippingStateRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/stateIds', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidationWithStringStateIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingStateRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'stateIds' => 'STATE-ID-1',
                        'operator' => ShippingStateRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/stateIds', $exceptions[0]['source']['pointer']);
            static::assertSame('This value should be of type array.', $exceptions[0]['detail']);
        }
    }

    public function testValidationWithArrayOfInvalidStateIdTypes(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new ShippingStateRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'stateIds' => ['STATE-ID-1', true, 3, Uuid::randomHex()],
                        'operator' => ShippingStateRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);
            static::assertSame('/0/value/stateIds', $exceptions[0]['source']['pointer']);
            static::assertSame('/0/value/stateIds', $exceptions[1]['source']['pointer']);
            static::assertSame('/0/value/stateIds', $exceptions[2]['source']['pointer']);

            static::assertSame('The value "STATE-ID-1" is not a valid uuid.', $exceptions[0]['detail']);
            static::assertSame('The value "1" is not a valid uuid.', $exceptions[1]['detail']);
            static::assertSame('The value "3" is not a valid uuid.', $exceptions[2]['detail']);
        }
    }

    public function testIfRuleIsConsistent(): void
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
                'type' => (new ShippingStateRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'stateIds' => [Uuid::randomHex(), Uuid::randomHex()],
                    'operator' => ShippingStateRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }
}

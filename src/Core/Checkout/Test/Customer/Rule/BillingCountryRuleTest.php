<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Rule\BillingCountryRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraints\NotBlank;

class BillingCountryRuleTest extends TestCase
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

    public function testValidationWithMissingCountryIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingCountryRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(2, $exception->getViolations());
                static::assertSame('/0/value/countryIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());

                static::assertSame('/0/value/operator', $exception->getViolations()->get(1)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(1)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(1)->getMessage());
            }
        }
    }

    public function testValidationWithEmptyCountryIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingCountryRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'countryIds' => [],
                        'operator' => BillingCountryRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/countryIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidationWithStringCountryIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingCountryRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'countryIds' => 'COUNTRY-ID-1',
                        'operator' => BillingCountryRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/countryIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should be of type array.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidationWithArrayOfInvalidCountryIdTypes(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new BillingCountryRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'countryIds' => ['COUNTRY-ID-1', true, 3, Uuid::randomHex()],
                        'operator' => BillingCountryRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(3, $exception->getViolations());
                static::assertSame('/0/value/countryIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('The value "COUNTRY-ID-1" is not a valid uuid.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('The value "1" is not a valid uuid.', $exception->getViolations()->get(1)->getMessage());
                static::assertSame('The value "3" is not a valid uuid.', $exception->getViolations()->get(2)->getMessage());
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
                'type' => (new BillingCountryRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'countryIds' => [Uuid::randomHex(), Uuid::randomHex()],
                    'operator' => BillingCountryRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }
}

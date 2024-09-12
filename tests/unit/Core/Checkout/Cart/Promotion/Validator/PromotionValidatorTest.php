<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Promotion\Validator\PromotionValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(PromotionValidator::class)]
class PromotionValidatorTest extends TestCase
{
    private WriteContext $context;

    private PromotionDefinition $promotionDefinition;

    private PromotionDiscountDefinition $discountDefinition;

    protected function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());

        $registry = new StaticDefinitionInstanceRegistry(
            [PromotionDefinition::class, PromotionDiscountDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        /** @var PromotionDefinition $promotionDefinition */
        $promotionDefinition = $registry->get(PromotionDefinition::class);

        /** @var PromotionDiscountDefinition $discountDefinition */
        $discountDefinition = $registry->get(PromotionDiscountDefinition::class);

        $this->promotionDefinition = $promotionDefinition;
        $this->discountDefinition = $discountDefinition;
    }

    /**
     * This test verifies that we do not allow a promotion that has
     * been configured to use a code, but the code is empty.
     * So we set useCodes to TRUE, provide an empty code and expect
     * a corresponding exception.
     */
    #[Group('promotions')]
    public function testPromotionCodeRequired(): void
    {
        $commands = [];
        $this->expectException(WriteException::class);

        $commands[] = new InsertCommand(
            $this->promotionDefinition,
            [
                'use_codes' => true,
                'use_individual_codes' => false,
                'code' => ' ',
            ],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $fakeConnection = new FakeConnection($this->getPromotionDbRows());

        $event = new PreWriteValidationEvent($this->context, $commands);
        $validator = new PromotionValidator($fakeConnection);
        $validator->preValidate($event);

        try {
            $event->getExceptions()->tryToThrow();
            static::fail('Validation with invalid until was not triggered.');
        } catch (WriteException $e) {
            static::assertCount(1, $e->getExceptions());

            $firstException = $e->getExceptions()[0];
            static::assertInstanceOf(WriteConstraintViolationException::class, $firstException);

            $violation = $firstException->getViolations()->get(0);

            static::assertEquals('/0/code', $violation->getPropertyPath());

            throw $e;
        }
    }

    /**
     * This test verifies that we get a correct exception if our
     * validUntil date is before the validFrom date.
     */
    #[Group('promotions')]
    public function testPromotionValidUntilAfterFrom(): void
    {
        $commands = [];
        $this->expectException(WriteException::class);

        $commands[] = new InsertCommand(
            $this->promotionDefinition,
            [
                'valid_from' => '2019-02-25 12:00:00',
                'valid_until' => '2019-02-25 11:59:59',
            ],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $fakeConnection = new FakeConnection($this->getPromotionDbRows());

        $event = new PreWriteValidationEvent($this->context, $commands);
        $validator = new PromotionValidator($fakeConnection);
        $validator->preValidate($event);

        try {
            $event->getExceptions()->tryToThrow();
            static::fail('Validation with invalid until was not triggered.');
        } catch (WriteException $e) {
            static::assertEquals(WriteConstraintViolationException::class, $e->getExceptions()[0]::class);

            throw $e;
        }
    }

    /**
     * This test verifies that we do not require a global code
     * if we have individual codes turned on.
     */
    #[Group('promotions')]
    public function testPromotionIndividualDoesNotRequireCode(): void
    {
        $commands = [];
        $commands[] = new InsertCommand(
            $this->promotionDefinition,
            [
                'use_codes' => true,
                'use_individual_codes' => true,
                'code' => ' ',
            ],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $fakeConnection = new FakeConnection($this->getPromotionDbRows());

        $event = new PreWriteValidationEvent($this->context, $commands);

        $validator = new PromotionValidator($fakeConnection);
        $validator->preValidate($event);

        static::expectNotToPerformAssertions();
    }

    /**
     * This test verifies that we get a correct exception when
     * sending invalid discount values to our validator.
     *
     * @throws \ReflectionException
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     * @throws WriteConstraintViolationException
     */
    #[DataProvider('invalidProvider')]
    #[Group('promotions')]
    public function testDiscountValueInvalid(string $type, float $value): void
    {
        $commands = [];
        $this->expectException(WriteException::class);

        $commands[] = new InsertCommand(
            $this->discountDefinition,
            [
                'type' => ($type === 'percentage') ? PromotionDiscountEntity::TYPE_PERCENTAGE : PromotionDiscountEntity::TYPE_ABSOLUTE,
                'value' => $value,
            ],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $fakeConnection = new FakeConnection($this->getPromotionDbRows());

        $event = new PreWriteValidationEvent($this->context, $commands);
        $validator = new PromotionValidator($fakeConnection);
        $validator->preValidate($event);

        try {
            $event->getExceptions()->tryToThrow();
            static::fail('Validation with invalid until was not triggered.');
        } catch (WriteException $e) {
            static::assertEquals(WriteConstraintViolationException::class, $e->getExceptions()[0]::class);

            throw $e;
        }
    }

    /**
     * @return array<string, array{0: string, 1: float}>
     */
    public static function invalidProvider(): array
    {
        return [
            'negative percentage' => ['percentage', -0.01],
            'percentage over 100' => ['percentage', 100.01],
            'negative absolute' => ['absolute', -0.01],
        ];
    }

    /**
     * This test verifies that we do not get an exception when
     * we send correct values within the allowed range to
     * our discount validator.
     * The value 0,00 is indeed allowed to make sure you can
     * use fixed prices of 0,00...and thus percentage and
     * absolute do also get that minValue (to make things easier).
     *
     * @throws \ReflectionException
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     * @throws WriteConstraintViolationException
     */
    #[DataProvider('validProvider')]
    #[Group('promotions')]
    public function testDiscountValueValid(string $type, float $value): void
    {
        $commands = [];
        $commands[] = new InsertCommand(
            $this->discountDefinition,
            [
                'type' => ($type === 'percentage') ? PromotionDiscountEntity::TYPE_PERCENTAGE : PromotionDiscountEntity::TYPE_ABSOLUTE,
                'value' => $value,
            ],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $fakeConnection = new FakeConnection($this->getPromotionDbRows());

        $event = new PreWriteValidationEvent($this->context, $commands);
        $validator = new PromotionValidator($fakeConnection);
        $validator->preValidate($event);
        $event->getExceptions()->tryToThrow();

        static::expectNotToPerformAssertions();
    }

    /**
     * @return array<string, array{0: string, 1: float}>
     */
    public static function validProvider(): array
    {
        return [
            'zero percentage' => ['percentage', -0.00],
            '100 percentage' => ['percentage', 100.00],
            'zero absolute' => ['absolute', 0.00],
            'positive absolute' => ['absolute', 260.00],
        ];
    }

    /**
     * Builds a fake database row entry that can be
     * used to return from the DBAL connection.
     *
     * @return list<array<string, mixed>>
     */
    private function getPromotionDbRows(): array
    {
        return [
            [
                'id' => Uuid::randomHex(),
                'valid_from' => '',
                'valid_until' => '',
                'use_codes' => true,
                'use_individual_codes' => true,
                'code' => 'ABC',
            ],
        ];
    }
}

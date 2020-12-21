<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Validator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Promotion\Validator\PromotionValidator;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes\FakeConnection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

class PromotionValidatorTest extends TestCase
{
    /** @var WriteContext */
    private $context;

    /** @var PromotionDefinition */
    private $promotionDefinition;

    /** @var PromotionDiscountDefinition */
    private $discountDefinition;

    public function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());

        $this->promotionDefinition = new PromotionDefinition();
        $this->discountDefinition = new PromotionDiscountDefinition();
    }

    /**
     * This test verifies that we do not allow a promotion that has
     * been configured to use a code, but the code is empty.
     * So we set useCodes to TRUE, provide an empty code and expect
     * a corresponding exception.
     *
     * @test
     * @group promotions
     */
    public function testPromotionCodeRequired(): void
    {
        $this->expectException(WriteException::class);

        $commands[] = new InsertCommand(
            $this->promotionDefinition,
            [
                'use_codes' => true,
                'use_individual_codes' => false,
                'code' => ' ',
            ],
            ['id' => 'D1'],
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
            static::assertEquals(WriteConstraintViolationException::class, \get_class($e->getExceptions()[0]));
            static::assertEquals('/0/code', $e->getExceptions()[0]->getViolations()[0]->getPropertyPath());

            throw $e;
        }
    }

    /**
     * This test verifies that we get a correct exception if our
     * validUntil date is before the validFrom date.
     *
     * @test
     * @group promotions
     */
    public function testPromotionValidUntilAfterFrom(): void
    {
        $this->expectException(WriteException::class);

        $commands[] = new InsertCommand(
            $this->promotionDefinition,
            [
                'valid_from' => '2019-02-25 12:00:00',
                'valid_until' => '2019-02-25 11:59:59',
            ],
            ['id' => 'D1'],
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
            static::assertEquals(WriteConstraintViolationException::class, \get_class($e->getExceptions()[0]));

            throw $e;
        }
    }

    /**
     * This test verifies that we do not require a global code
     * if we have individual codes turned on.
     *
     * @test
     * @group promotions
     */
    public function testPromotionIndividualDoesNotRequireCode(): void
    {
        $commands[] = new InsertCommand(
            $this->promotionDefinition,
            [
                'use_codes' => true,
                'use_individual_codes' => true,
                'code' => ' ',
            ],
            ['id' => 'D1'],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $fakeConnection = new FakeConnection($this->getPromotionDbRows());

        $event = new PreWriteValidationEvent($this->context, $commands);

        $validator = new PromotionValidator($fakeConnection);
        $validator->preValidate($event);

        static::assertTrue(true);
    }

    /**
     * This test verifies that we get a correct exception when
     * sending invalid discount values to our validator.
     *
     * @test
     * @group promotions
     * @testWith ["percentage", -0.01]
     *           ["percentage", 100.01]
     *           ["absolute", -0.01]
     *
     * @throws \ReflectionException
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     * @throws WriteConstraintViolationException
     */
    public function testDiscountValueInvalid(string $type, float $value): void
    {
        $this->expectException(WriteException::class);

        $commands[] = new InsertCommand(
            $this->discountDefinition,
            [
                'type' => ($type === 'percentage') ? PromotionDiscountEntity::TYPE_PERCENTAGE : PromotionDiscountEntity::TYPE_ABSOLUTE,
                'value' => $value,
            ],
            ['id' => 'D1'],
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
            static::assertEquals(WriteConstraintViolationException::class, \get_class($e->getExceptions()[0]));

            throw $e;
        }
    }

    /**
     * This test verifies that we do not get an exception when
     * we send correct values within the allowed range to
     * our discount validator.
     * The value 0,00 is indeed allowed to make sure you can
     * use fixed prices of 0,00...and thus percentage and
     * absolute do also get that minValue (to make things easier).
     *
     * @test
     * @group promotions
     * @testWith ["percentage", 0.00]
     *           ["percentage", 100]
     *           ["absolute", 0.00]
     *           ["absolute", 260.0]
     *
     * @throws \ReflectionException
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     * @throws WriteConstraintViolationException
     */
    public function testDiscountValueValid(string $type, float $value): void
    {
        $commands[] = new InsertCommand(
            $this->discountDefinition,
            [
                'type' => ($type === 'percentage') ? PromotionDiscountEntity::TYPE_PERCENTAGE : PromotionDiscountEntity::TYPE_ABSOLUTE,
                'value' => $value,
            ],
            ['id' => 'D1'],
            $this->createMock(EntityExistence::class),
            '/0'
        );

        $fakeConnection = new FakeConnection($this->getPromotionDbRows());

        $event = new PreWriteValidationEvent($this->context, $commands);
        $validator = new PromotionValidator($fakeConnection);
        $validator->preValidate($event);
        $event->getExceptions()->tryToThrow();

        static::assertTrue(true);
    }

    /**
     * Builds a fake database row entry that can be
     * used to return from the DBAL connection.
     *
     * @return array
     */
    private function getPromotionDbRows()
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

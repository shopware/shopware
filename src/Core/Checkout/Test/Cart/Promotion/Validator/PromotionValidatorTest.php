<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Validator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Promotion\Validator\PromotionValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
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
    public function testPromotionCodeRequired()
    {
        $this->expectException(WriteConstraintViolationException::class);

        $commands[] = new InsertCommand(
            $this->promotionDefinition,
            [
                'use_codes' => true,
                'code' => ' ',
            ],
            ['id' => 'D1'],
            $this->createMock(EntityExistence::class)
        );

        $validator = new PromotionValidator();
        $validator->preValidate($commands, $this->context);
    }

    /**
     * This test verifies that we get a correct exception if our
     * validUntil date is before the validFrom date.
     *
     * @test
     * @group promotions
     */
    public function testPromotionValidUntilAfterFrom()
    {
        $this->expectException(WriteConstraintViolationException::class);

        $commands[] = new InsertCommand(
            $this->promotionDefinition,
            [
                'valid_from' => '2019-02-25 12:00:00',
                'valid_until' => '2019-02-25 11:59:59',
            ],
            ['id' => 'D1'],
            $this->createMock(EntityExistence::class)
        );

        $validator = new PromotionValidator();
        $validator->preValidate($commands, $this->context);
    }

    /**
     * This test verifies that we get a correct exception when
     * sending invalid discount values to our validator.
     *
     * @test
     * @group promotions
     * @testWith ["percentage", -0.01]
     *           ["percentage", 0]
     *           ["percentage", 100.01]
     *           ["absolute", -0.01]
     *           ["absolute", 0.0]
     *
     * @throws \ReflectionException
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     * @throws WriteConstraintViolationException
     */
    public function testDiscountValueInvalid(string $type, float $value)
    {
        $this->expectException(WriteConstraintViolationException::class);

        $commands[] = new InsertCommand(
            $this->discountDefinition,
            [
                'type' => ($type === 'percentage') ? PromotionDiscountEntity::TYPE_PERCENTAGE : PromotionDiscountEntity::TYPE_ABSOLUTE,
                'value' => $value,
            ],
            ['id' => 'D1'],
            $this->createMock(EntityExistence::class)
        );

        $validator = new PromotionValidator();
        $validator->preValidate($commands, $this->context);
    }

    /**
     * This test verifies that we do not get an exception when
     * we send correct values within the allowed range to
     * our discount validator.
     *
     * @test
     * @group promotions
     * @testWith ["percentage", 0.01]
     *           ["percentage", 100]
     *           ["absolute", 0.01]
     *           ["absolute", 260.0]
     *
     * @throws \ReflectionException
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     * @throws WriteConstraintViolationException
     */
    public function testDiscountValueValid(string $type, float $value)
    {
        $commands[] = new InsertCommand(
            $this->discountDefinition,
            [
                'type' => ($type === 'percentage') ? PromotionDiscountEntity::TYPE_PERCENTAGE : PromotionDiscountEntity::TYPE_ABSOLUTE,
                'value' => $value,
            ],
            ['id' => 'D1'],
            $this->createMock(EntityExistence::class)
        );

        $validator = new PromotionValidator();
        $validator->preValidate($commands, $this->context);

        static::assertTrue(true);
    }
}

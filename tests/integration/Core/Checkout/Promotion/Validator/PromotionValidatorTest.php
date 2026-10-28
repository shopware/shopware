<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Promotion\Validator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Assert\WriteViolations;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionValidatorTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testAPromotionWithGlobalCodesNeedsACode(): void
    {
        WriteViolations::assertRejected(['PROMOTION_EMPTY_CODE_VIOLATION'], fn () => $this->createPromotion(['useCodes' => true, 'code' => ' ']));
    }

    public function testACodeCannotStartOrEndWithWhitespace(): void
    {
        WriteViolations::assertRejected(['PROMOTION_CODE_WHITESPACE_VIOLATION'], fn () => $this->createPromotion(['useCodes' => true, 'code' => ' summer ']));
    }

    public function testAPromotionWithIndividualCodesNeedsNoGlobalCode(): void
    {
        WriteViolations::assertAccepted(fn () => $this->createPromotion(['useCodes' => true, 'useIndividualCodes' => true]));
    }

    public function testAPromotionCannotEndBeforeItStarts(): void
    {
        WriteViolations::assertRejected(['PROMOTION_VALID_UNTIL_VIOLATION'], fn () => $this->createPromotion([
            'validFrom' => '2030-06-01 00:00:00',
            'validUntil' => '2030-01-01 00:00:00',
        ]));
    }

    public function testDatesSetByTwoWritesOfOneRequestAreCheckedTogether(): void
    {
        $this->createPromotion(['validFrom' => '2030-01-01 00:00:00', 'validUntil' => '2030-12-31 00:00:00']);

        WriteViolations::assertRejected(['PROMOTION_VALID_UNTIL_VIOLATION'], fn () => static::getContainer()->get(SyncService::class)->sync([
            new SyncOperation('from', 'promotion', SyncOperation::ACTION_UPSERT, [['id' => $this->ids->get('promotion'), 'validFrom' => '2030-06-01 00:00:00']]),
            new SyncOperation('until', 'promotion', SyncOperation::ACTION_UPSERT, [['id' => $this->ids->get('promotion'), 'validUntil' => '2030-03-01 00:00:00']]),
        ], Context::createDefaultContext(), new SyncBehavior()));
    }

    public function testACodeCannotBeUsedByTwoPromotions(): void
    {
        $this->createPromotion(['id' => $this->ids->get('existing'), 'useCodes' => true, 'code' => 'summer']);

        WriteViolations::assertRejected(['PROMOTION_DUPLICATED_CODE_VIOLATION'], fn () => $this->createPromotion(['useCodes' => true, 'code' => 'summer']));
    }

    public function testAnIndividualCodePatternCannotBeUsedByTwoPromotions(): void
    {
        $this->createPromotion(['id' => $this->ids->get('existing'), 'useCodes' => true, 'useIndividualCodes' => true, 'individualCodePattern' => 'SUMMER_%d']);

        WriteViolations::assertRejected(['PROMOTION_DUPLICATE_PATTERN_VIOLATION'], fn () => $this->createPromotion([
            'useCodes' => true,
            'useIndividualCodes' => true,
            'individualCodePattern' => 'SUMMER_%d',
        ]));
    }

    #[DataProvider('invalidDiscounts')]
    public function testADiscountOutsideTheAllowedRangeIsRejected(string $type, float $value, string $code): void
    {
        WriteViolations::assertRejected([$code], fn () => $this->createPromotion(['discounts' => [$this->discount($type, $value)]]));
    }

    /**
     * @return iterable<string, array{string, float, string}>
     */
    public static function invalidDiscounts(): iterable
    {
        yield 'negative percentage' => [PromotionDiscountEntity::TYPE_PERCENTAGE, -0.01, 'PROMOTION_DISCOUNT_MIN_VALUE_VIOLATION'];
        yield 'percentage over 100' => [PromotionDiscountEntity::TYPE_PERCENTAGE, 100.01, 'PROMOTION_DISCOUNT_MAX_VALUE_VIOLATION'];
        yield 'negative absolute' => [PromotionDiscountEntity::TYPE_ABSOLUTE, -0.01, 'PROMOTION_DISCOUNT_MIN_VALUE_VIOLATION'];
    }

    #[DataProvider('validDiscounts')]
    public function testADiscountWithinTheAllowedRangeIsAccepted(string $type, float $value): void
    {
        WriteViolations::assertAccepted(fn () => $this->createPromotion(['discounts' => [$this->discount($type, $value)]]));
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function validDiscounts(): iterable
    {
        yield 'zero percentage' => [PromotionDiscountEntity::TYPE_PERCENTAGE, 0.0];
        yield '100 percentage' => [PromotionDiscountEntity::TYPE_PERCENTAGE, 100.0];
        yield 'zero absolute' => [PromotionDiscountEntity::TYPE_ABSOLUTE, 0.0];
        yield 'absolute over 100' => [PromotionDiscountEntity::TYPE_ABSOLUTE, 260.0];
    }

    public function testAViolationPointsAtTheFieldOfTheDiscount(): void
    {
        try {
            $this->createPromotion(['discounts' => [$this->discount(PromotionDiscountEntity::TYPE_PERCENTAGE, 120.0)]]);
            static::fail('Expected the write to be rejected.');
        } catch (WriteException $exception) {
            static::assertSame(['/0/discounts/0/value'], array_column(array_column(iterator_to_array($exception->getErrors()), 'source'), 'pointer'));
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function createPromotion(array $values): void
    {
        static::getContainer()->get('promotion.repository')->create([[
            'id' => $this->ids->get('promotion'),
            'name' => 'promotion',
            'active' => true,
            ...$values,
        ]], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function discount(string $type, float $value): array
    {
        return ['scope' => PromotionDiscountEntity::SCOPE_CART, 'type' => $type, 'value' => $value, 'considerAdvancedRules' => false];
    }
}

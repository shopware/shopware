<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(PromotionException::class)]
class PromotionExceptionTest extends TestCase
{
    public function testCodeAlreadyRedeemed(): void
    {
        try {
            throw PromotionException::codeAlreadyRedeemed('code-123');
        } catch (PromotionException $e) {
            static::assertSame(PromotionException::PROMOTION_CODE_ALREADY_REDEEMED, $e->getErrorCode());
            static::assertSame('Promotion with code "code-123" has already been marked as redeemed!', $e->getMessage());
            static::assertSame(['code' => 'code-123'], $e->getParameters());
        }
    }

    public function testInvalidCodePattern(): void
    {
        try {
            throw PromotionException::invalidCodePattern('code-123');
        } catch (PromotionException $e) {
            static::assertSame(PromotionException::INVALID_CODE_PATTERN, $e->getErrorCode());
            static::assertSame('Invalid code pattern "code-123".', $e->getMessage());
            static::assertSame(['codePattern' => 'code-123'], $e->getParameters());
        }
    }

    public function testPatternNotComplexEnough(): void
    {
        try {
            throw PromotionException::patternNotComplexEnough();
        } catch (PromotionException $e) {
            static::assertSame(PromotionException::PATTERN_NOT_COMPLEX_ENOUGH, $e->getErrorCode());
            static::assertSame('The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.', $e->getMessage());
            static::assertEmpty($e->getParameters());
        }
    }

    public function testPatternAlreadyInUse(): void
    {
        try {
            throw PromotionException::patternAlreadyInUse();
        } catch (PromotionException $e) {
            static::assertSame(PromotionException::PATTERN_ALREADY_IN_USE, $e->getErrorCode());
            static::assertSame('Code pattern already exists in another promotion. Please provide a different pattern.', $e->getMessage());
            static::assertEmpty($e->getParameters());
        }
    }

    public function testPromotionsNotFound(): void
    {
        try {
            throw PromotionException::promotionsNotFound(['promotion-123', 'promotion-456']);
        } catch (PromotionException $e) {
            static::assertSame(PromotionException::PROMOTION_NOT_FOUND, $e->getErrorCode());
            static::assertSame('These promotions "promotion-123, promotion-456" are not found', $e->getMessage());
            static::assertSame(['ids' => 'promotion-123, promotion-456'], $e->getParameters());
        }
    }

    public function testDiscountsNotFound(): void
    {
        try {
            throw PromotionException::discountsNotFound(['promotion-123', 'promotion-456']);
        } catch (PromotionException $e) {
            static::assertSame(PromotionException::PROMOTION_DISCOUNT_NOT_FOUND, $e->getErrorCode());
            static::assertSame('These promotion discounts "promotion-123, promotion-456" are not found', $e->getMessage());
            static::assertSame(['ids' => 'promotion-123, promotion-456'], $e->getParameters());
        }
    }

    public function testPromotionCodeNotFound(): void
    {
        try {
            throw PromotionException::promotionCodeNotFound('code-123');
        } catch (PromotionException $e) {
            static::assertSame(PromotionException::PROMOTION_CODE_NOT_FOUND, $e->getErrorCode());
            static::assertSame('Promotion code "code-123" has not been found!', $e->getMessage());
            static::assertSame(['code' => 'code-123'], $e->getParameters());
        }
    }
}

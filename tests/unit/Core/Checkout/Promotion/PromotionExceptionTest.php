<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Exception\InvalidCodePatternException;
use Shopware\Core\Checkout\Promotion\Exception\PatternNotComplexEnoughException;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(PromotionException::class)]
class PromotionExceptionTest extends TestCase
{
    public function testCodeAlreadyRedeemed(): void
    {
        $exception = PromotionException::codeAlreadyRedeemed('code-123');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::PROMOTION_CODE_ALREADY_REDEEMED, $exception->getErrorCode());
        static::assertSame('Promotion with code "code-123" has already been marked as redeemed!', $exception->getMessage());
        static::assertSame(['code' => 'code-123'], $exception->getParameters());
    }

    public function testInvalidCodePattern(): void
    {
        $exception = PromotionException::invalidCodePattern('code-123');

        static::assertInstanceOf(InvalidCodePatternException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::INVALID_CODE_PATTERN, $exception->getErrorCode());
        static::assertSame('Invalid code pattern "code-123".', $exception->getMessage());
        static::assertSame(['codePattern' => 'code-123'], $exception->getParameters());
    }

    public function testPatternNotComplexEnough(): void
    {
        $exception = PromotionException::patternNotComplexEnough();

        static::assertInstanceOf(PatternNotComplexEnoughException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::PATTERN_NOT_COMPLEX_ENOUGH, $exception->getErrorCode());
        static::assertSame('The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testPatternAlreadyInUse(): void
    {
        $exception = PromotionException::patternAlreadyInUse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::PATTERN_ALREADY_IN_USE, $exception->getErrorCode());
        static::assertSame('Code pattern already exists in another promotion. Please provide a different pattern.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }

    public function testPromotionsNotFound(): void
    {
        $exception = PromotionException::promotionsNotFound(['promotion-123', 'promotion-456']);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(PromotionException::PROMOTION_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('These promotions "promotion-123, promotion-456" are not found', $exception->getMessage());
        static::assertSame(['ids' => 'promotion-123, promotion-456'], $exception->getParameters());
    }

    public function testDiscountsNotFound(): void
    {
        $exception = PromotionException::discountsNotFound(['promotion-123', 'promotion-456']);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(PromotionException::PROMOTION_DISCOUNT_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('These promotion discounts "promotion-123, promotion-456" are not found', $exception->getMessage());
        static::assertSame(['ids' => 'promotion-123, promotion-456'], $exception->getParameters());
    }

    public function testPromotionCodeNotFound(): void
    {
        $exception = PromotionException::promotionCodeNotFound('code-123');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::PROMOTION_CODE_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Promotion code "code-123" has not been found!', $exception->getMessage());
        static::assertSame(['code' => 'code-123'], $exception->getParameters());
    }
}

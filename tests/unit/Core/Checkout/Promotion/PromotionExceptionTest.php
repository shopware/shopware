<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Exception\FilterPickerNotFoundException;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Exception\FilterSorterNotFoundException;
use Shopware\Core\Checkout\Promotion\Exception\DiscountCalculatorNotFoundException;
use Shopware\Core\Checkout\Promotion\Exception\InvalidCodePatternException;
use Shopware\Core\Checkout\Promotion\Exception\InvalidScopeDefinitionException;
use Shopware\Core\Checkout\Promotion\Exception\PatternNotComplexEnoughException;
use Shopware\Core\Checkout\Promotion\Exception\PriceNotFoundException;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionException::class)]
class PromotionExceptionTest extends TestCase
{
    public function testCodeAlreadyRedeemed(): void
    {
        $exception = PromotionException::codeAlreadyRedeemed('code-123');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::PROMOTION_CODE_ALREADY_REDEEMED, $exception->getErrorCode());
        static::assertSame('Promo code "code-123" has already been marked as redeemed!', $exception->getMessage());
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

    public function testUnknownPromotionDiscountType(): void
    {
        $promotion = new PromotionDiscountEntity();
        $promotion->setType('test');

        $exception = PromotionException::unknownPromotionDiscountType($promotion);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::CHECKOUT_UNKNOWN_PROMOTION_DISCOUNT_TYPE, $exception->getErrorCode());
        static::assertSame('Unknown promotion discount type detected: test', $exception->getMessage());
        static::assertSame(['type' => 'test'], $exception->getParameters());
    }

    public function testPromotionSetGroupNotFound(): void
    {
        $exception = PromotionException::promotionSetGroupNotFound('fooGroupId');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::PROMOTION_SET_GROUP_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Promotion SetGroup "fooGroupId" has not been found!', $exception->getMessage());
        static::assertSame(['id' => 'fooGroupId'], $exception->getParameters());
    }

    public function testDiscountCalculatorNotFound(): void
    {
        $exception = PromotionException::discountCalculatorNotFound('type-123');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::DISCOUNT_CALCULATOR_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Promotion Discount Calculator "type-123" has not been found!', $exception->getMessage());
        static::assertSame(['type' => 'type-123'], $exception->getParameters());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDiscountCalculatorNotFoundDeprecated(): void
    {
        $exception = PromotionException::discountCalculatorNotFound('type-123');

        static::assertInstanceOf(DiscountCalculatorNotFoundException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__DISCOUNT_CALCULATOR_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('Promotion Discount Calculator "type-123" has not been found!', $exception->getMessage());
        static::assertSame(['type' => 'type-123'], $exception->getParameters());
    }

    public function testInvalidScopeDefinition(): void
    {
        $exception = PromotionException::invalidScopeDefinition('bad-scope');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::INVALID_DISCOUNT_SCOPE_DEFINITION, $exception->getErrorCode());
        static::assertSame('Invalid discount calculator scope definition "bad-scope"', $exception->getMessage());
        static::assertSame(['label' => 'bad-scope'], $exception->getParameters());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInvalidScopeDefinitionDeprecated(): void
    {
        $exception = PromotionException::invalidScopeDefinition('bad-scope');

        static::assertInstanceOf(InvalidScopeDefinitionException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CHECKOUT__INVALID_DISCOUNT_SCOPE_DEFINITION', $exception->getErrorCode());
        static::assertSame('Invalid discount calculator scope definition "bad-scope"', $exception->getMessage());
        static::assertSame(['label' => 'bad-scope'], $exception->getParameters());
    }

    public function testMissingRequestParameter(): void
    {
        $exception = PromotionException::missingRequestParameter('parameter');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::MISSING_REQUEST_PARAMETER_CODE, $exception->getErrorCode());
        static::assertSame('Parameter "parameter" is missing.', $exception->getMessage());
        static::assertSame(['parameterName' => 'parameter'], $exception->getParameters());
    }

    public function testPriceNotFound(): void
    {
        $exception = PromotionException::priceNotFound(new LineItem('test', 'test'));

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertInstanceOf(PriceNotFoundException::class, $exception);
        }

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::PRICE_NOT_FOUND_FOR_ITEM, $exception->getErrorCode());
        static::assertSame('No calculated price found for item test', $exception->getMessage());
        static::assertSame(['id' => 'test'], $exception->getParameters());
    }

    public function testFilterSorterNotFound(): void
    {
        $exception = PromotionException::filterSorterNotFound('filter-123');

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertInstanceOf(FilterSorterNotFoundException::class, $exception);
        }

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::FILTER_SORTER_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Sorter "filter-123" has not been found!', $exception->getMessage());
        static::assertSame(['key' => 'filter-123'], $exception->getParameters());
    }

    public function testFilterPickerNotFoundException(): void
    {
        $exception = PromotionException::filterPickerNotFoundException('filter-123');

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertInstanceOf(FilterPickerNotFoundException::class, $exception);
        }

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(PromotionException::FILTER_PICKER_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Picker "filter-123" has not been found!', $exception->getMessage());
        static::assertSame(['key' => 'filter-123'], $exception->getParameters());
    }
}

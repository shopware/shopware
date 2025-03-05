<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Exception\DiscountCalculatorNotFoundException;
use Shopware\Core\Checkout\Promotion\Exception\InvalidCodePatternException;
use Shopware\Core\Checkout\Promotion\Exception\InvalidScopeDefinitionException;
use Shopware\Core\Checkout\Promotion\Exception\PatternNotComplexEnoughException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class PromotionException extends HttpException
{
    public const PROMOTION_CODE_ALREADY_REDEEMED = 'CHECKOUT__CODE_ALREADY_REDEEMED';

    public const DISCOUNT_CALCULATOR_NOT_FOUND = 'CHECKOUT__PROMOTION_DISCOUNT_CALCULATOR_NOT_FOUND';

    public const INVALID_CODE_PATTERN = 'CHECKOUT__INVALID_CODE_PATTERN';

    public const INVALID_DISCOUNT_SCOPE_DEFINITION = 'CHECKOUT__PROMOTION_INVALID_DISCOUNT_SCOPE_DEFINITION';

    public const PATTERN_NOT_COMPLEX_ENOUGH = 'PROMOTION__INDIVIDUAL_CODES_PATTERN_INSUFFICIENTLY_COMPLEX';

    public const PATTERN_ALREADY_IN_USE = 'PROMOTION__INDIVIDUAL_CODES_PATTERN_ALREADY_IN_USE';

    public const PROMOTION_NOT_FOUND = 'CHECKOUT__PROMOTION__NOT_FOUND';

    public const PROMOTION_DISCOUNT_NOT_FOUND = 'CHECKOUT__PROMOTION_DISCOUNT_NOT_FOUND';

    public const PROMOTION_CODE_NOT_FOUND = 'CHECKOUT__PROMOTION_CODE_NOT_FOUND';

    public const PROMOTION_INVALID_PRICE_DEFINITION = 'CHECKOUT__INVALID_DISCOUNT_PRICE_DEFINITION';

    public const CHECKOUT_UNKNOWN_PROMOTION_DISCOUNT_TYPE = 'CHECKOUT__UNKNOWN_PROMOTION_DISCOUNT_TYPE';

    public const PROMOTION_SET_GROUP_NOT_FOUND = 'CHECKOUT__PROMOTION_SETGROUP_NOT_FOUND';

    public static function codeAlreadyRedeemed(string $code): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROMOTION_CODE_ALREADY_REDEEMED,
            'Promo code "{{ code }}" has already been marked as redeemed!',
            ['code' => $code]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function discountCalculatorNotFound(string $type): self|DiscountCalculatorNotFoundException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new DiscountCalculatorNotFoundException($type);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DISCOUNT_CALCULATOR_NOT_FOUND,
            'Promotion Discount Calculator "{{ type }}" has not been found!',
            ['type' => $type]
        );
    }

    public static function invalidCodePattern(string $codePattern): self
    {
        return new InvalidCodePatternException(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CODE_PATTERN,
            'Invalid code pattern "{{ codePattern }}".',
            ['codePattern' => $codePattern]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function invalidScopeDefinition(string $scope): self|InvalidScopeDefinitionException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new InvalidScopeDefinitionException($scope);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DISCOUNT_SCOPE_DEFINITION,
            'Invalid discount calculator scope definition "{{ label }}"',
            ['label' => $scope]
        );
    }

    public static function patternNotComplexEnough(): self
    {
        return new PatternNotComplexEnoughException(
            Response::HTTP_BAD_REQUEST,
            self::PATTERN_NOT_COMPLEX_ENOUGH,
            'The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.'
        );
    }

    public static function patternAlreadyInUse(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PATTERN_ALREADY_IN_USE,
            'Code pattern already exists in another promotion. Please provide a different pattern.'
        );
    }

    /**
     * @param string[] $ids
     */
    public static function promotionsNotFound(array $ids): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::PROMOTION_NOT_FOUND,
            'These promotions "{{ ids }}" are not found',
            ['ids' => implode(', ', $ids)]
        );
    }

    /**
     * @param string[] $ids
     */
    public static function discountsNotFound(array $ids): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::PROMOTION_DISCOUNT_NOT_FOUND,
            'These promotion discounts "{{ ids }}" are not found',
            ['ids' => implode(', ', $ids)]
        );
    }

    public static function promotionCodeNotFound(string $code): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROMOTION_CODE_NOT_FOUND,
            'Promotion code "{{ code }}" has not been found!',
            ['code' => $code]
        );
    }

    /**
     * @param list<string> $codes
     */
    public static function promotionCodesNotFound(array $codes): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROMOTION_CODE_NOT_FOUND,
            'None of the promotion codes "{{ code }}" have not been found!',
            ['code' => \implode(', ', $codes)]
        );
    }

    public static function invalidPriceDefinition(string $label, ?string $code): self
    {
        if ($code === null) {
            $messages = [
                'Invalid discount price definition for automated promotion "{{ label }}"',
                ['label' => $label],
            ];
        } else {
            $messages = [
                'Invalid discount price definition for promotion line item with code "{{ code }}"',
                ['code' => $code],
            ];
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROMOTION_INVALID_PRICE_DEFINITION,
            ...$messages,
        );
    }

    public static function unknownPromotionDiscountType(PromotionDiscountEntity $discount): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHECKOUT_UNKNOWN_PROMOTION_DISCOUNT_TYPE,
            'Unknown promotion discount type detected: {{ type }}',
            ['type' => $discount->getType()]
        );
    }

    public static function promotionSetGroupNotFound(string $groupId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROMOTION_SET_GROUP_NOT_FOUND,
            'Promotion SetGroup "{{ id }}" has not been found!',
            ['id' => $groupId],
        );
    }
}

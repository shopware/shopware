<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Checkout\Promotion\Exception\PatternAlreadyInUseException;
use Shopware\Core\Checkout\Promotion\Exception\PatternNotComplexEnoughException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class PromotionException extends HttpException
{
    public const PROMOTION_CODE_ALREADY_REDEEMED = 'CHECKOUT__CODE_ALREADY_REDEEMED';

    public const INVALID_CODE_PATTERN = 'CHECKOUT__INVALID_CODE_PATTERN';

    public const PATTERN_NOT_COMPLEX_ENOUGH = 'PROMOTION__INDIVIDUAL_CODES_PATTERN_INSUFFICIENTLY_COMPLEX';

    public const PATTERN_ALREADY_IN_USE = 'PROMOTION__INDIVIDUAL_CODES_PATTERN_ALREADY_IN_USE';

    public static function codeAlreadyRedeemed(string $code): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROMOTION_CODE_ALREADY_REDEEMED,
            'Promotion with code "{{ code }}" has already been marked as redeemed!',
            ['code' => $code]
        );
    }

    public static function invalidCodePattern(string $codePattern): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CODE_PATTERN,
            'Invalid code pattern "{{ codePattern }}".',
            ['codePattern' => $codePattern]
        );
    }

    public static function patternNotComplexEnough(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new PatternNotComplexEnoughException();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PATTERN_NOT_COMPLEX_ENOUGH,
            'The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.'
        );
    }

    public static function patternAlreadyInUse(): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new PatternAlreadyInUseException();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PATTERN_ALREADY_IN_USE,
            'Code pattern already exists in another promotion. Please provide a different pattern.'
        );
    }
}

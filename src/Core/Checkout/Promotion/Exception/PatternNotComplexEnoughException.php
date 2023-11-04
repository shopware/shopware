<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use PromotionException::patternNotComplexEnough instead
 */
#[Package('checkout')]
class PatternNotComplexEnoughException extends PromotionException
{
    final public const ERROR_CODE = 'PROMOTION__INDIVIDUAL_CODES_PATTERN_INSUFFICIENTLY_COMPLEX';

    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use PromotionException::patternNotComplexEnough instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::PATTERN_NOT_COMPLEX_ENOUGH,
            'The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.'
        );
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use PromotionException::patternAlreadyInUse instead
 */
#[Package('checkout')]
class PatternAlreadyInUseException extends PromotionException
{
    final public const ERROR_CODE = 'PROMOTION__INDIVIDUAL_CODES_PATTERN_ALREADY_IN_USE';

    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use PromotionException::patternAlreadyInUse instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::PATTERN_ALREADY_IN_USE,
            'Code pattern already exists in another promotion. Please provide a different pattern.'
        );
    }
}

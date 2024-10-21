<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use PromotionException::invalidPriceDefinition instead
 */
#[Package('buyers-experience')]
class InvalidPriceDefinitionException extends ShopwareHttpException
{
    public function __construct(
        string $label,
        ?string $code
    ) {
        if ($code === null) {
            parent::__construct(
                'Invalid discount price definition for automated promotion "{{ label }}"',
                ['label' => $label]
            );

            return;
        }

        parent::__construct(
            'Invalid discount price definition for promotion line item with code "{{ code }}"',
            ['code' => $code]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'PromotionException::invalidPriceDefinition')
        );

        return 'CHECKOUT__INVALID_DISCOUNT_PRICE_DEFINITION';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'PromotionException::invalidPriceDefinition')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}

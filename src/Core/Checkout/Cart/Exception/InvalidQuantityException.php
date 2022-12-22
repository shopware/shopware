<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package checkout
 *
 * @deprecated tag:v6.5.0 - Will be removed. Exception is not used anymore.
 */
class InvalidQuantityException extends ShopwareHttpException
{
    public function __construct(int $quantity)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );
        parent::__construct(
            'The quantity must be a positive integer. Given: "{{ quantity }}"',
            ['quantity' => $quantity]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return 'CHECKOUT__CART_INVALID_LINEITEM_QUANTITY';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}

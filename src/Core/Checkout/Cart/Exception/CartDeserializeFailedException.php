<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @package checkout
 *
 * @deprecated tag:v6.5.0 - Use \Shopware\Core\Checkout\Cart\CartException::deserializeFailed instead. Class will be removed and CartException will be thrown instead
 */
class CartDeserializeFailedException extends ShopwareHttpException
{
    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );
        parent::__construct('Failed to deserialize cart.');
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return 'CHECKOUT__CART_DESERIALIZE_FAILED';
    }
}

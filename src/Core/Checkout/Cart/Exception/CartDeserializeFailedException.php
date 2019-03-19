<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CartDeserializeFailedException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Failed to deserialize cart.');
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_DESERIALIZE_FAILED';
    }
}

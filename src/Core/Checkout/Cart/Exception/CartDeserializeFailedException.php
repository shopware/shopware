<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

class CartDeserializeFailedException extends ShopwareHttpException
{
    protected $code = 'CART-DESERIALIZE-FAILED';

    public function __construct($code = 0, Throwable $previous = null)
    {
        parent::__construct('Failed to deserialize cart', $code, $previous);
    }
}

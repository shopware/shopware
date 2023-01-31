<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('checkout')]
class CartResponse extends StoreApiResponse
{
    /**
     * @var Cart
     */
    protected $object;

    public function __construct(Cart $object)
    {
        parent::__construct($object);
    }

    public function getCart(): Cart
    {
        return $this->object;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;

class Validator
{
    public function validate(): ErrorCollection
    {
        return new ErrorCollection();
    }
}

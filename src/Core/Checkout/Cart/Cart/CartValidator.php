<?php declare(strict_types=1);

namespace Shopware\Checkout\Cart\Cart;

use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;

class CartValidator
{
    /**
     * @var CartValidatorInterface[]
     */
    private $validators;

    public function __construct(iterable $validators)
    {
        $this->validators = $validators;
    }

    public function isValid(CalculatedCart $cart, StorefrontContext $context): bool
    {
        $valid = true;

        foreach ($this->validators as $validator) {
            if (!$validator->isValid($cart, $context)) {
                $valid = false;
            }
        }

        return $valid;
    }
}

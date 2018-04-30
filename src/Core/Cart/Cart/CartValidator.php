<?php declare(strict_types=1);

namespace Shopware\Cart\Cart;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\Struct\StorefrontContext;

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

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

class RestrictDeleteViolation
{
    /**
     * Contains an array which indexed by definition class.
     * Each value represents a single restricted identity
     *
     * Example:
     *      [Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition] => Array
     *          (
     *              [0] => c708bb9dc2c34243b9fb1fd6a676f2fb
     *              [1] => c708bb9dc2c34243b9fb1fd6a676f2fb
     *          )
     *      [Shopware\Core\Content\Product\ProductDefinition] => Array
     *          (
     *              [0] => c708bb9dc2c34243b9fb1fd6a676f2fb
     *              [1] => c708bb9dc2c34243b9fb1fd6a676f2fb
     *          )
     *
     * @var array[]
     */
    private $restrictions;

    public function __construct(array $restrictions)
    {
        $this->restrictions = $restrictions;
    }

    public function getRestrictions(): array
    {
        return $this->restrictions;
    }
}

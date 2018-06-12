<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Content\Product\Cart\Struct\CalculatedProduct;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;

use Shopware\Core\Framework\Rule\RuleScope;

class ProductOfManufacturerRule extends Rule
{
    /**
     * @var string
     */
    protected $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function match(RuleScope $scope): Match
    {
        if (!$scope instanceof CalculatedLineItemScope) {
            return new Match(
                false,
                ['Invalid Match Context. CalculatedLineItemScope expected']
            );
        }

        $calculatedLineItem = $scope->getCalculatedLineItem();
        if (!$calculatedLineItem instanceof CalculatedProduct) {
            return new Match(
                false,
                ['CalculatedLineItem is not a CalculatedProduct']
            );
        }

        /** @var $calculatedLineItem CalculatedProduct */
        $manufacturer = $calculatedLineItem->getProduct()->getManufacturer();
        if ($manufacturer && $manufacturer->getId() == $this->id) {
            return new Match(true);
        }

        return new Match(
            false,
            ['CalculatedProduct manufacturer id does not match']
        );
    }
}

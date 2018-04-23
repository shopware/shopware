<?php declare(strict_types=1);

namespace Shopware\Context\Rule\CalculatedLineItem;

use Shopware\CartBridge\Product\Struct\CalculatedProduct;
use Shopware\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Context\MatchContext\RuleMatchContext;
use Shopware\Context\Rule\Match;
use Shopware\Context\Rule\Rule;

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

    public function match(
        RuleMatchContext $matchContext
    ): Match {
        if (!$matchContext instanceof CalculatedLineItemMatchContext) {
            return new Match(
                false,
                ['Invalid Match Context. CalculatedLineItemMatchContext expected']
            );
        }

        $calculatedLineItem = $matchContext->getCalculatedLineItem();
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

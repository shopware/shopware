<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Collection;

use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeDetailStruct;
use Shopware\Core\Content\Rule\Collection\RuleBasicCollection;

class DiscountSurchargeDetailCollection extends DiscountSurchargeBasicCollection
{
    /**
     * @var DiscountSurchargeDetailStruct[]
     */
    protected $elements = [];

    public function getRules(): RuleBasicCollection
    {
        return new RuleBasicCollection(
            $this->fmap(function (DiscountSurchargeDetailStruct $discountSurcharge) {
                return $discountSurcharge->getRule();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeDetailStruct::class;
    }
}

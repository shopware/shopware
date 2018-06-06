<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Collection;

use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeDetailStruct;
use Shopware\Core\Content\Rule\Collection\ContextRuleBasicCollection;

class DiscountSurchargeDetailCollection extends DiscountSurchargeBasicCollection
{
    /**
     * @var DiscountSurchargeDetailStruct[]
     */
    protected $elements = [];

    public function getContextRules(): ContextRuleBasicCollection
    {
        return new ContextRuleBasicCollection(
            $this->fmap(function (DiscountSurchargeDetailStruct $discountSurcharge) {
                return $discountSurcharge->getContextRule();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeDetailStruct::class;
    }
}

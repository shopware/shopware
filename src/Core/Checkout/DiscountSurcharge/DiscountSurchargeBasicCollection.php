<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class DiscountSurchargeBasicCollection extends EntityCollection
{
    /**
     * @var DiscountSurchargeBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? DiscountSurchargeBasicStruct
    {
        return parent::get($id);
    }

    public function current(): DiscountSurchargeBasicStruct
    {
        return parent::current();
    }

    public function getRuleIds(): array
    {
        return $this->fmap(function (DiscountSurchargeBasicStruct $discountSurcharge) {
            return $discountSurcharge->getRuleId();
        });
    }

    public function filterByRuleId(string $id): self
    {
        return $this->filter(function (DiscountSurchargeBasicStruct $discountSurcharge) use ($id) {
            return $discountSurcharge->getRuleId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeBasicStruct::class;
    }
}

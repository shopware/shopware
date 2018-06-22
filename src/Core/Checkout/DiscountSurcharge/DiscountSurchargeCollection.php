<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge;

use Shopware\Core\Framework\ORM\EntityCollection;

class DiscountSurchargeCollection extends EntityCollection
{
    /**
     * @var DiscountSurchargeStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? DiscountSurchargeStruct
    {
        return parent::get($id);
    }

    public function current(): DiscountSurchargeStruct
    {
        return parent::current();
    }

    public function getRuleIds(): array
    {
        return $this->fmap(function (DiscountSurchargeStruct $discountSurcharge) {
            return $discountSurcharge->getRuleId();
        });
    }

    public function filterByRuleId(string $id): self
    {
        return $this->filter(function (DiscountSurchargeStruct $discountSurcharge) use ($id) {
            return $discountSurcharge->getRuleId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeStruct::class;
    }
}

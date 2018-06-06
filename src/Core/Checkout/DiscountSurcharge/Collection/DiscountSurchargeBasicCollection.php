<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Collection;

use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeBasicStruct;
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

    public function getContextRuleIds(): array
    {
        return $this->fmap(function (DiscountSurchargeBasicStruct $discountSurcharge) {
            return $discountSurcharge->getContextRuleId();
        });
    }

    public function filterByContextRuleId(string $id): self
    {
        return $this->filter(function (DiscountSurchargeBasicStruct $discountSurcharge) use ($id) {
            return $discountSurcharge->getContextRuleId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeBasicStruct::class;
    }
}

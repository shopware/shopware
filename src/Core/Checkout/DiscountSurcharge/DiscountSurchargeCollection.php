<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class DiscountSurchargeCollection extends EntityCollection
{
    /**
     * @var DiscountSurchargeEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? DiscountSurchargeEntity
    {
        return parent::get($id);
    }

    public function current(): DiscountSurchargeEntity
    {
        return parent::current();
    }

    public function getRuleIds(): array
    {
        return $this->fmap(function (DiscountSurchargeEntity $discountSurcharge) {
            return $discountSurcharge->getRuleId();
        });
    }

    public function filterByRuleId(string $id): self
    {
        return $this->filter(function (DiscountSurchargeEntity $discountSurcharge) use ($id) {
            return $discountSurcharge->getRuleId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return DiscountSurchargeEntity::class;
    }
}

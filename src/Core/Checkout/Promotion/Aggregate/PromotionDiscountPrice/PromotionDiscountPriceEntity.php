<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyEntity;

#[Package('checkout')]
class PromotionDiscountPriceEntity extends Entity
{
    use EntityIdTrait;

    protected string $currencyId;

    protected string $discountId;

    protected float $price;

    protected PromotionDiscountEntity $promotionDiscount;

    protected CurrencyEntity $currency;

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getDiscountId(): string
    {
        return $this->discountId;
    }

    public function setDiscountId(string $discountId): void
    {
        $this->discountId = $discountId;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getCurrency(): CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getPromotionDiscount(): PromotionDiscountEntity
    {
        return $this->promotionDiscount;
    }

    public function setPromotionDiscount(PromotionDiscountEntity $promotionDiscount): void
    {
        $this->promotionDiscount = $promotionDiscount;
    }
}

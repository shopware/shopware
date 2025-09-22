<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class DiscountCampaignStruct extends StoreStruct
{
    protected string $name;

    protected \DateTimeImmutable $startDate;

    protected \DateTimeImmutable $endDate;

    protected float $discount;

    protected float $discountedPrice;

    protected float $discountedPricePerMonth;

    protected ?int $discountAppliesForMonths = null;

    /**
     * @return DiscountCampaignStruct
     */
    public static function fromArray(array $data): StoreStruct
    {
        $discountCampaign = (new self())->assign($data);

        if (isset($data['startDate']) && \is_string($data['startDate'])) {
            $discountCampaign->setStartDate(new \DateTimeImmutable($data['startDate']));
        }
        if (isset($data['endDate']) && \is_string($data['endDate'])) {
            $discountCampaign->setEndDate(new \DateTimeImmutable($data['endDate']));
        }

        return $discountCampaign;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): void
    {
        $this->discount = $discount;
    }

    public function getDiscountedPrice(): float
    {
        return $this->discountedPrice;
    }

    public function setDiscountedPrice(float $discountedPrice): void
    {
        $this->discountedPrice = $discountedPrice;
    }

    public function getDiscountedPricePerMonth(): float
    {
        return $this->discountedPricePerMonth;
    }

    public function setDiscountedPricePerMonth(float $discountedPricePerMonth): void
    {
        $this->discountedPricePerMonth = $discountedPricePerMonth;
    }

    public function getDiscountAppliesForMonths(): ?int
    {
        return $this->discountAppliesForMonths;
    }

    public function setDiscountAppliesForMonths(?int $discountAppliesForMonths): void
    {
        $this->discountAppliesForMonths = $discountAppliesForMonths;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class DiscountCampaignStruct extends StoreStruct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var \DateTimeImmutable
     */
    protected $startDate;

    /**
     * @var \DateTimeImmutable
     */
    protected $endDate;

    /**
     * @var float
     */
    protected $discount;

    /**
     * @var float
     */
    protected $discountedPrice;

    /**
     * @var int|null
     */
    protected $discountAppliesForMonths;

    public static function fromArray(array $data): StoreStruct
    {
        return (new self())->assign($data);
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

    public function getDiscountAppliesForMonths(): ?int
    {
        return $this->discountAppliesForMonths;
    }

    public function setDiscountAppliesForMonths(?int $discountAppliesForMonths): void
    {
        $this->discountAppliesForMonths = $discountAppliesForMonths;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class VariantStruct extends StoreStruct
{
    final public const TYPE_RENT = 'rent';
    final public const TYPE_BUY = 'buy';
    final public const TYPE_FREE = 'free';
    final public const RENT_DURATION_MONTHLY = 1;
    final public const RENT_DURATION_YEARLY = 12;

    protected int $id;

    protected string $type;

    protected float $netPrice;

    protected float $netPricePerMonth;

    protected bool $trialPhaseIncluded = false;

    protected int $duration;

    protected ?DiscountCampaignStruct $discountCampaign = null;

    /**
     * @return VariantStruct
     */
    public static function fromArray(array $data): StoreStruct
    {
        $variant = (new self())->assign($data);

        if (isset($data['discountCampaign']) && \is_array($data['discountCampaign'])) {
            $variant->setDiscountCampaign(DiscountCampaignStruct::fromArray($data['discountCampaign']));
        }

        return $variant;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getNetPrice(): float
    {
        return $this->netPrice;
    }

    public function getNetPricePerMonth(): float
    {
        return $this->netPricePerMonth;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function isTrialPhaseIncluded(): bool
    {
        return $this->trialPhaseIncluded;
    }

    public function setTrialPhaseIncluded(bool $trialPhaseIncluded): void
    {
        $this->trialPhaseIncluded = $trialPhaseIncluded;
    }

    public function getDiscountCampaign(): ?DiscountCampaignStruct
    {
        return $this->discountCampaign;
    }

    public function setDiscountCampaign(?DiscountCampaignStruct $discountCampaign): void
    {
        $this->discountCampaign = $discountCampaign;
    }
}

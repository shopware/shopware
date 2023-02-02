<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

/**
 * @codeCoverageIgnore
 */
class VariantStruct extends StoreStruct
{
    public const TYPE_RENT = 'rent';
    public const TYPE_BUY = 'buy';
    public const TYPE_FREE = 'free';

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var float
     */
    protected $netPrice;

    /**
     * @var bool
     */
    protected $trialPhaseIncluded = false;

    /**
     * @var DiscountCampaignStruct|null
     */
    protected $discountCampaign;

    public static function fromArray(array $data): StoreStruct
    {
        $variant = new self();

        return $variant->assign($data);
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

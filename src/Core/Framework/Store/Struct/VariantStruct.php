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

    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $id;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $type;

    /**
     * @var float
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $netPrice;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $trialPhaseIncluded = false;

    /**
     * @var DiscountCampaignStruct|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $discountCampaign;

    /**
     * @return VariantStruct
     */
    public static function fromArray(array $data): StoreStruct
    {
        return (new self())->assign($data);
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

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class Price extends Struct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $currencyId;

    /**
     * @var float
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $net;

    /**
     * @var float
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $gross;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $linked;

    /**
     * @var Price|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $listPrice;

    /**
     * @var array|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $percentage;

    /**
     * @var Price|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $regulationPrice;

    public function __construct(
        string $currencyId,
        float $net,
        float $gross,
        bool $linked,
        ?Price $listPrice = null,
        ?array $percentage = null,
        ?Price $regulationPrice = null
    ) {
        $this->net = $net;
        $this->gross = $gross;
        $this->linked = $linked;
        $this->currencyId = $currencyId;
        $this->listPrice = $listPrice;
        $this->percentage = $percentage;
        $this->regulationPrice = $regulationPrice;
    }

    public function getNet(): float
    {
        return $this->net;
    }

    public function setNet(float $net): void
    {
        $this->net = $net;
    }

    public function getGross(): float
    {
        return $this->gross;
    }

    public function setGross(float $gross): void
    {
        $this->gross = $gross;
    }

    public function getLinked(): bool
    {
        return $this->linked;
    }

    public function setLinked(bool $linked): void
    {
        $this->linked = $linked;
    }

    public function add(self $price): void
    {
        $this->gross += $price->getGross();
        $this->net += $price->getNet();
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function setListPrice(?Price $listPrice): void
    {
        $this->listPrice = $listPrice;
    }

    public function getListPrice(): ?Price
    {
        return $this->listPrice;
    }

    public function getPercentage(): ?array
    {
        return $this->percentage;
    }

    public function setPercentage(?array $percentage): void
    {
        $this->percentage = $percentage;
    }

    public function getApiAlias(): string
    {
        return 'price';
    }

    public function getRegulationPrice(): ?Price
    {
        return $this->regulationPrice;
    }

    public function setRegulationPrice(?Price $regulationPrice): void
    {
        $this->regulationPrice = $regulationPrice;
    }
}

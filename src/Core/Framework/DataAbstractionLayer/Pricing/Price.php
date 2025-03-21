<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class Price extends Struct
{
    public function __construct(
        protected string $currencyId,
        protected float $net,
        protected float $gross,
        protected bool $linked,
        protected ?Price $listPrice = null,
        protected ?array $percentage = null,
        protected ?Price $regulationPrice = null
    ) {
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

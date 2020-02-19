<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Framework\Struct\Struct;

class Price extends Struct
{
    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var float
     */
    protected $net;

    /**
     * @var float
     */
    protected $gross;

    /**
     * @var bool
     */
    protected $linked;

    /**
     * @var Price|null
     */
    protected $listPrice;

    public function __construct(string $currencyId, float $net, float $gross, bool $linked, ?Price $listPrice = null)
    {
        $this->net = $net;
        $this->gross = $gross;
        $this->linked = $linked;
        $this->currencyId = $currencyId;
        $this->listPrice = $listPrice;
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

    public function getApiAlias(): string
    {
        return 'price';
    }
}

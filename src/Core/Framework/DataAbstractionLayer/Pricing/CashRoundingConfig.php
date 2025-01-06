<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class CashRoundingConfig extends Struct
{
    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $decimals;

    /**
     * @var float
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $interval;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $roundForNet;

    public function __construct(
        int $decimals,
        float $interval,
        bool $roundForNet
    ) {
        $this->decimals = $decimals;
        $this->interval = $interval;
        $this->roundForNet = $roundForNet;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    public function setDecimals(int $decimals): void
    {
        $this->decimals = $decimals;
    }

    public function getInterval(): float
    {
        return $this->interval;
    }

    public function setInterval(float $interval): void
    {
        $this->interval = $interval;
    }

    public function roundForNet(): bool
    {
        return $this->roundForNet;
    }

    public function setRoundForNet(bool $roundForNet): void
    {
        $this->roundForNet = $roundForNet;
    }
}

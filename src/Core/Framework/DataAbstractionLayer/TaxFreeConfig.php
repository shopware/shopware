<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal (FEATURE_NEXT_14114)
 */
class TaxFreeConfig extends Struct
{
    protected bool $enabled;

    protected string $currencyId;

    protected float $amount;

    public function __construct(bool $enabled = false, string $currencyId = Defaults::CURRENCY, float $amount = 0)
    {
        $this->enabled = $enabled;
        $this->currencyId = $currencyId;
        $this->amount = $amount;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }
}

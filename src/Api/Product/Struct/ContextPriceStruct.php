<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Framework\Struct\Struct;

class ContextPriceStruct extends Struct
{
    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var int
     */
    protected $quantityStart;

    /**
     * @var int|null
     */
    protected $quantityEnd;

    /**
     * @var string
     */
    protected $ruleId;

    /**
     * @var float
     */
    protected $gross;

    /**
     * @var float
     */
    protected $net;

    public function __construct(
        string $currencyId,
        int $quantityStart,
        ?int $quantityEnd,
        string $ruleId,
        float $gross,
        float $net
    ) {
        $this->currencyId = $currencyId;
        $this->quantityStart = $quantityStart;
        $this->quantityEnd = $quantityEnd;
        $this->ruleId = $ruleId;
        $this->gross = $gross;
        $this->net = $net;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getQuantityStart(): int
    {
        return $this->quantityStart;
    }

    public function setQuantityStart(int $quantityStart): void
    {
        $this->quantityStart = $quantityStart;
    }

    public function getQuantityEnd(): ?int
    {
        return $this->quantityEnd;
    }

    public function setQuantityEnd(?int $quantityEnd): void
    {
        $this->quantityEnd = $quantityEnd;
    }

    public function getRuleId(): string
    {
        return $this->ruleId;
    }

    public function setRuleId(string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getGross(): float
    {
        return $this->gross;
    }

    public function setGross(float $gross): void
    {
        $this->gross = $gross;
    }

    public function getNet(): float
    {
        return $this->net;
    }

    public function setNet(float $net): void
    {
        $this->net = $net;
    }
}

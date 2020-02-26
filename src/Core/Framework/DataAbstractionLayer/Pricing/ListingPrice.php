<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Pricing;

use Shopware\Core\Framework\Struct\Struct;

class ListingPrice extends Struct
{
    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var string
     */
    protected $ruleId;

    /**
     * @var Price
     */
    protected $from;

    /**
     * @var Price
     */
    protected $to;

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getRuleId(): string
    {
        return $this->ruleId;
    }

    public function setRuleId(string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getFrom(): Price
    {
        return $this->from;
    }

    public function setFrom(Price $from): void
    {
        $this->from = $from;
    }

    public function getTo(): Price
    {
        return $this->to;
    }

    public function setTo(Price $to): void
    {
        $this->to = $to;
    }

    public function isDifferent(): bool
    {
        return $this->getTo()->getGross() !== $this->getFrom()->getGross();
    }

    public function getApiAlias(): string
    {
        return 'listing_price';
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Pricing;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PriceRuleEntity extends Entity
{
    use EntityIdTrait;
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
    protected $price;

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

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function setPrice(Price $price): void
    {
        $this->price = $price;
    }
}

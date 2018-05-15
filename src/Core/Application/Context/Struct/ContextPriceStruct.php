<?php declare(strict_types=1);

namespace Shopware\Application\Context\Struct;

use Shopware\Api\Entity\Entity;
use Shopware\Content\Product\Struct\PriceStruct;

class ContextPriceStruct extends Entity
{
    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var string
     */
    protected $contextRuleId;

    /**
     * @var PriceStruct
     */
    protected $price;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getContextRuleId(): string
    {
        return $this->contextRuleId;
    }

    public function setContextRuleId(string $contextRuleId): void
    {
        $this->contextRuleId = $contextRuleId;
    }

    public function getPrice(): PriceStruct
    {
        return $this->price;
    }

    public function setPrice(PriceStruct $price): void
    {
        $this->price = $price;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}

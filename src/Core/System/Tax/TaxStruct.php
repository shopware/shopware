<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;

class TaxStruct extends Entity
{
    /**
     * @var float
     */
    protected $rate;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var EntitySearchResult|null
     */
    protected $areaRules;

    public function getRate(): float
    {
        return $this->rate;
    }

    public function setRate(float $rate): void
    {
        $this->rate = $rate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
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

    public function getAreaRules(): ?EntitySearchResult
    {
        return $this->areaRules;
    }

    public function setAreaRules(EntitySearchResult $areaRules): void
    {
        $this->areaRules = $areaRules;
    }
}

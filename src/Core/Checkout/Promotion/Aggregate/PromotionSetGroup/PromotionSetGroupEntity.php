<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup;

use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionSetGroupEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $packagerKey;

    /**
     * @var string
     */
    protected $sorterKey;

    /**
     * @var float
     */
    protected $value;

    /**
     * @var string
     */
    protected $promotionId;

    /**
     * @var PromotionEntity|null
     */
    protected $promotion;

    /**
     * @var RuleCollection|null
     */
    protected $setGroupRules;

    public function getPackagerKey(): string
    {
        return $this->packagerKey;
    }

    public function setPackagerKey(string $packagerKey): void
    {
        $this->packagerKey = $packagerKey;
    }

    public function getSorterKey(): string
    {
        return $this->sorterKey;
    }

    public function setSorterKey(string $sorterKey): void
    {
        $this->sorterKey = $sorterKey;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): void
    {
        $this->value = $value;
    }

    public function getPromotionId(): string
    {
        return $this->promotionId;
    }

    public function setPromotionId(string $promotionId): void
    {
        $this->promotionId = $promotionId;
    }

    public function getPromotion(): ?PromotionEntity
    {
        return $this->promotion;
    }

    public function setPromotion(?PromotionEntity $promotion): void
    {
        $this->promotion = $promotion;
    }

    public function getSetGroupRules(): ?RuleCollection
    {
        return $this->setGroupRules;
    }

    public function setSetGroupRules(RuleCollection $setGroupRules): void
    {
        $this->setGroupRules = $setGroupRules;
    }
}

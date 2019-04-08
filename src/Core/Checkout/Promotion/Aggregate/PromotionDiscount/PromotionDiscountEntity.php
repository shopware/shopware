<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount;

use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PromotionDiscountEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $promotionId;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var float
     */
    protected $value;

    /**
     * @var bool
     */
    protected $graduated;

    /**
     * @var int|null
     */
    protected $graduationStep;

    /**
     * @var string|null
     */
    protected $graduationOrder;

    /**
     * @var PromotionEntity|null
     */
    protected $promotion;

    /**
     * @var string
     */
    protected $applyTowards;

    // TODO $applyTowardsSingleGroupId, once promotion-group entity exists

    public function getPromotionId(): string
    {
        return $this->promotionId;
    }

    public function setPromotionId(string $promotionId): void
    {
        $this->promotionId = $promotionId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): void
    {
        $this->value = $value;
    }

    public function isGraduated(): bool
    {
        return $this->graduated;
    }

    public function setGraduated(bool $graduated): void
    {
        $this->graduated = $graduated;
    }

    public function getGraduationStep(): ?int
    {
        return $this->graduationStep;
    }

    public function setGraduationStep(int $graduationStep): void
    {
        $this->graduationStep = $graduationStep;
    }

    public function getGraduationOrder(): ?string
    {
        return $this->graduationOrder;
    }

    public function setGraduationOrder(string $graduationOrder): void
    {
        $this->graduationOrder = $graduationOrder;
    }

    public function getPromotion(): ?PromotionEntity
    {
        return $this->promotion;
    }

    public function setPromotion(PromotionEntity $promotion): void
    {
        $this->promotion = $promotion;
    }

    public function getApplyTowards(): string
    {
        return $this->applyTowards;
    }

    public function setApplyTowards(string $applyTowards): void
    {
        $this->applyTowards = $applyTowards;
    }
}

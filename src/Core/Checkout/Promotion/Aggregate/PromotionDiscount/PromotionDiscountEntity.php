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
     * This scope defines promotion discounts on
     * the entire cart and its line items.
     */
    public const SCOPE_CART = 'cart';

    /**
     * This type defines a percentage
     * price definition of the discount.
     */
    public const TYPE_PERCENTAGE = 'percentage';

    /**
     * This type defines an absolute price
     * definition of the discount in the
     * current context currency.
     */
    public const TYPE_ABSOLUTE = 'absolute';

    /**
     * @var string
     */
    protected $promotionId;

    /**
     * @var string
     */
    protected $scope;

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

    public function getPromotionId(): string
    {
        return $this->promotionId;
    }

    public function setPromotionId(string $promotionId): void
    {
        $this->promotionId = $promotionId;
    }

    /**
     * Gets the scope of this discount.
     * This is basically the affected area where the
     * discount is being used on.
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * Sets the scope that is being affected
     * by the value of this discount.
     */
    public function setScope(string $scope): void
    {
        $this->scope = $scope;
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
}

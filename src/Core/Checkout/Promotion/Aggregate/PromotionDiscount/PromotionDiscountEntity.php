<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount;

use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
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
     * @var PromotionEntity|null
     */
    protected $promotion;

    /**
     * @var RuleCollection|null
     */
    protected $discountRules;

    /**
     * @var bool
     */
    protected $considerAdvancedRules;

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

    public function getPromotion(): ?PromotionEntity
    {
        return $this->promotion;
    }

    public function setPromotion(PromotionEntity $promotion): void
    {
        $this->promotion = $promotion;
    }

    public function getDiscountRules(): ?RuleCollection
    {
        return $this->discountRules;
    }

    public function setDiscountRules(?RuleCollection $discountRules): void
    {
        $this->discountRules = $discountRules;
    }

    public function isConsiderAdvancedRules(): bool
    {
        if ($this->considerAdvancedRules === null) {
            return false;
        }

        return $this->considerAdvancedRules;
    }

    public function setConsiderAdvancedRules(bool $considerAdvancedRules): void
    {
        $this->considerAdvancedRules = $considerAdvancedRules;
    }
}

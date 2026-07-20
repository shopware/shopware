<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PromotionDiscountEntity extends Entity
{
    use EntityIdTrait;

    /**
     * This scope defines promotion discounts on
     * the entire cart and its line items.
     */
    final public const SCOPE_CART = 'cart';

    /**
     * This scope defines promotion discounts on
     * the delivery costs.
     */
    final public const SCOPE_DELIVERY = 'delivery';

    /**
     * This scope defines promotion discounts on
     * the whole set of groups
     */
    final public const SCOPE_SET = 'set';

    /**
     * This scope defines promotion discounts on
     * a specific set group.
     */
    final public const SCOPE_SETGROUP = 'setgroup';

    /**
     * This type defines a percentage
     * price definition of the discount.
     */
    final public const TYPE_PERCENTAGE = 'percentage';

    /**
     * This type defines an absolute price
     * definition of the discount in the
     * current context currency.
     */
    final public const TYPE_ABSOLUTE = 'absolute';

    /**
     * This type defines an fixed item price
     * definition of the discount.
     */
    final public const TYPE_FIXED_UNIT = 'fixed_unit';

    /**
     * This type defines a fixed price
     * definition of the discount.
     */
    final public const TYPE_FIXED = 'fixed';

    protected string $promotionId;

    protected string $scope;

    protected string $type;

    protected float $value;

    protected ?PromotionEntity $promotion = null;

    protected ?RuleCollection $discountRules = null;

    protected bool $considerAdvancedRules;

    protected ?float $maxValue = null;

    protected ?PromotionDiscountPriceCollection $promotionDiscountPrices = null;

    protected ?string $sorterKey = null;

    protected ?string $applierKey = null;

    protected ?string $usageKey = null;

    protected ?string $pickerKey = null;

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

    public function setDiscountRules(RuleCollection $discountRules): void
    {
        $this->discountRules = $discountRules;
    }

    /**
     * if a promotionDiscountPrice has a value for a currency this value should be
     * taken for the discount value and not the value of this entity
     */
    public function getPromotionDiscountPrices(): ?PromotionDiscountPriceCollection
    {
        return $this->promotionDiscountPrices;
    }

    public function setPromotionDiscountPrices(PromotionDiscountPriceCollection $promotionDiscountPrices): void
    {
        $this->promotionDiscountPrices = $promotionDiscountPrices;
    }

    public function isConsiderAdvancedRules(): bool
    {
        return $this->considerAdvancedRules;
    }

    public function setConsiderAdvancedRules(bool $considerAdvancedRules): void
    {
        $this->considerAdvancedRules = $considerAdvancedRules;
    }

    /**
     * Gets the maximum discount value
     * of a percentage discount if set for the promotion.
     */
    public function getMaxValue(): ?float
    {
        return $this->maxValue;
    }

    /**
     * Sets a maximum discount value for the promotion.
     * This one will be used to as a threshold for percentage discounts.
     */
    public function setMaxValue(?float $maxValue): void
    {
        $this->maxValue = $maxValue;
    }

    /**
     * Gets if the scope is set to a custom setgroup.
     * The scope contains the groupId, so a prefix
     * match must occur.
     */
    public function isScopeSetGroup(): bool
    {
        $prefix = PromotionDiscountEntity::SCOPE_SETGROUP . '-';

        return mb_strpos($this->scope, $prefix) === 0;
    }

    /**
     * Gets the assigned groupId if
     * the discount scope has been set to setgroup.
     */
    public function getSetGroupId(): string
    {
        if (!$this->isScopeSetGroup()) {
            return '';
        }

        $prefix = PromotionDiscountEntity::SCOPE_SETGROUP . '-';

        return str_replace($prefix, '', $this->scope);
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return `?string` in the future
     * @deprecated tag:v6.8.0 - reason:behavior-change - The fallback to empty string will be removed
     */
    public function getSorterKey(): string
    {
        // @deprecated tag:v6.8.0 - The fallback to empty string will be removed
        return $this->sorterKey ?? '';
    }

    public function setSorterKey(?string $sorterKey): void
    {
        $this->sorterKey = $sorterKey;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return `?string` in the future
     * @deprecated tag:v6.8.0 - reason:behavior-change - The fallback to empty string will be removed
     */
    public function getApplierKey(): string
    {
        // @deprecated tag:v6.8.0 - The fallback to empty string will be removed
        return $this->applierKey ?? '';
    }

    public function setApplierKey(?string $applierKey): void
    {
        $this->applierKey = $applierKey;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return `?string` in the future
     * @deprecated tag:v6.8.0 - reason:behavior-change - The fallback to empty string will be removed
     */
    public function getUsageKey(): string
    {
        // @deprecated tag:v6.8.0 - The fallback to empty string will be removed
        return $this->usageKey ?? '';
    }

    public function setUsageKey(?string $usageKey): void
    {
        $this->usageKey = $usageKey;
    }

    public function getPickerKey(): string
    {
        return (string) $this->pickerKey;
    }

    public function setPickerKey(string $pickerKey): void
    {
        $this->pickerKey = $pickerKey;
    }
}

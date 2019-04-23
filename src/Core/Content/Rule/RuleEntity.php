<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Rule\Rule;

class RuleEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var int
     */
    protected $priority;

    /**
     * @var string|Rule|null
     */
    protected $payload;

    /**
     * @var \DateTimeInterface
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @var array|null
     */
    protected $moduleTypes;

    /**
     * @var DiscountSurchargeCollection|null
     */
    protected $discountSurcharges;

    /**
     * @var ProductPriceCollection|null
     */
    protected $productPrices;

    /**
     * @var ShippingMethodCollection|null
     */
    protected $shippingMethods;

    /**
     * @var PaymentMethodCollection|null
     */
    protected $paymentMethods;

    /**
     * @var RuleConditionCollection|null
     */
    protected $conditions;

    /**
     * @var bool
     */
    protected $invalid;

    /**
     * @var array|null
     */
    protected $attributes;

    /**
     * @var ShippingMethodPriceCollection|null
     */
    protected $shippingMethodPrices;

    /**
     * @var PromotionCollection|null
     */
    protected $scopePromotions;

    /**
     * @var PromotionCollection|null
     */
    protected $discountPromotions;

    /**
     * @var ShippingMethodPriceCollection|null
     */
    protected $shippingMethodPriceCalculations;

    /**
     * @var PromotionCollection|null
     */
    protected $personaPromotions;

    /**
     * @var PromotionCollection|null
     */
    protected $orderPromotions;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function setPayload($payload): void
    {
        $this->payload = $payload;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDiscountSurcharges(): ?DiscountSurchargeCollection
    {
        return $this->discountSurcharges;
    }

    public function setDiscountSurcharges(DiscountSurchargeCollection $discountSurcharges): void
    {
        $this->discountSurcharges = $discountSurcharges;
    }

    public function getProductPrices(): ?ProductPriceCollection
    {
        return $this->productPrices;
    }

    public function setProductPrices(ProductPriceCollection $productPrices): void
    {
        $this->productPrices = $productPrices;
    }

    public function getShippingMethods(): ?ShippingMethodCollection
    {
        return $this->shippingMethods;
    }

    public function setShippingMethods(ShippingMethodCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }

    public function getPaymentMethods(): ?PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(?PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getConditions(): ?RuleConditionCollection
    {
        return $this->conditions;
    }

    public function setConditions(RuleConditionCollection $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function isInvalid(): bool
    {
        return $this->invalid;
    }

    public function setInvalid(bool $invalid): void
    {
        $this->invalid = $invalid;
    }

    public function getModuleTypes(): ?array
    {
        return $this->moduleTypes;
    }

    public function setModuleTypes(?array $moduleTypes): void
    {
        $this->moduleTypes = $moduleTypes;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getShippingMethodPrices(): ?ShippingMethodPriceCollection
    {
        return $this->shippingMethodPrices;
    }

    public function setShippingMethodPrices(ShippingMethodPriceCollection $shippingMethodPrices): void
    {
        $this->shippingMethodPrices = $shippingMethodPrices;
    }

    public function getScopePromotions(): ?PromotionCollection
    {
        return $this->scopePromotions;
    }

    public function setScopePromotions(PromotionCollection $scopePromotions): void
    {
        $this->scopePromotions = $scopePromotions;
    }

    public function getDiscountPromotions(): ?PromotionCollection
    {
        return $this->discountPromotions;
    }

    public function setDiscountPromotions(PromotionCollection $discountPromotions): void
    {
        $this->discountPromotions = $discountPromotions;
    }

    public function getShippingMethodPriceCalculations(): ?ShippingMethodPriceCollection
    {
        return $this->shippingMethodPriceCalculations;
    }

    public function setShippingMethodPriceCalculations(ShippingMethodPriceCollection $shippingMethodPriceCalculations): void
    {
        $this->shippingMethodPriceCalculations = $shippingMethodPriceCalculations;
    }

    /**
     * Gets a list of all promotions where this rule
     * is being used within the Persona Conditions
     */
    public function getPersonaPromotions(): ?PromotionCollection
    {
        return $this->personaPromotions;
    }

    /**
     * Sets a list of all promotions where this rule should be
     * used as Persona Condition
     */
    public function setPersonaPromotions(?PromotionCollection $personaPromotions): void
    {
        $this->personaPromotions = $personaPromotions;
    }

    /**
     * Gets a list of all promotions where this rule is
     * being used within the Order Conditions.
     */
    public function getOrderPromotions(): ?PromotionCollection
    {
        return $this->orderPromotions;
    }

    /**
     * Sets a list of all promotions where this rule should be
     * used as Order Condition.
     */
    public function setOrderPromotions(?PromotionCollection $orderPromotions): void
    {
        $this->orderPromotions = $orderPromotions;
    }
}

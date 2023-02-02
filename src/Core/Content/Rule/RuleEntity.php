<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupCollection;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Event\EventAction\EventActionCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\Tag\TagCollection;

class RuleEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

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
     * @deprecated tag:v6.5.0 - Will be internal from 6.5.0 onward
     *
     * @var string|Rule|null
     */
    protected $payload;

    /**
     * @var array|null
     */
    protected $moduleTypes;

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
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
     *
     * @var EventActionCollection|null
     */
    protected $eventActions;

    /**
     * @var RuleConditionCollection|null
     */
    protected $conditions;

    /**
     * @var bool
     */
    protected $invalid;

    /**
     * @var ShippingMethodPriceCollection|null
     */
    protected $shippingMethodPrices;

    /**
     * @var PromotionDiscountCollection|null
     */
    protected $promotionDiscounts;

    /**
     * @var PromotionSetGroupCollection|null
     */
    protected $promotionSetGroups;

    /**
     * @var ShippingMethodPriceCollection|null
     */
    protected $shippingMethodPriceCalculations;

    /**
     * @var PromotionCollection|null
     */
    protected $personaPromotions;

    /**
     * @var FlowSequenceCollection|null
     */
    protected $flowSequences;

    /**
     * @var TagCollection|null
     */
    protected $tags;

    /**
     * @var PromotionCollection|null
     */
    protected $orderPromotions;

    /**
     * @var PromotionCollection|null
     */
    protected $cartPromotions;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal from 6.5.0 onward
     */
    public function getPayload()
    {
        $this->checkIfPropertyAccessIsAllowed('payload');

        return $this->payload;
    }

    /**
     * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal from 6.5.0 onward
     */
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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
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

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
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

    public function getShippingMethodPrices(): ?ShippingMethodPriceCollection
    {
        return $this->shippingMethodPrices;
    }

    public function setShippingMethodPrices(ShippingMethodPriceCollection $shippingMethodPrices): void
    {
        $this->shippingMethodPrices = $shippingMethodPrices;
    }

    public function getPromotionDiscounts(): ?PromotionDiscountCollection
    {
        return $this->promotionDiscounts;
    }

    public function setPromotionDiscounts(PromotionDiscountCollection $promotionDiscounts): void
    {
        $this->promotionDiscounts = $promotionDiscounts;
    }

    public function getPromotionSetGroups(): ?PromotionSetGroupCollection
    {
        return $this->promotionSetGroups;
    }

    public function setPromotionSetGroups(PromotionSetGroupCollection $promotionSetGroups): void
    {
        $this->promotionSetGroups = $promotionSetGroups;
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
    public function setPersonaPromotions(PromotionCollection $personaPromotions): void
    {
        $this->personaPromotions = $personaPromotions;
    }

    public function getFlowSequences(): ?FlowSequenceCollection
    {
        return $this->flowSequences;
    }

    public function setFlowSequences(FlowSequenceCollection $flowSequences): void
    {
        $this->flowSequences = $flowSequences;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
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
    public function setOrderPromotions(PromotionCollection $orderPromotions): void
    {
        $this->orderPromotions = $orderPromotions;
    }

    /**
     * Gets a list of all promotions where this rule is
     * being used within the Cart Conditions.
     */
    public function getCartPromotions(): ?PromotionCollection
    {
        return $this->cartPromotions;
    }

    /**
     * Sets a list of all promotions where this rule should be
     * used as Cart Condition.
     */
    public function setCartPromotions(PromotionCollection $cartPromotions): void
    {
        $this->cartPromotions = $cartPromotions;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
     */
    public function getEventActions(): ?EventActionCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->eventActions;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
     */
    public function setEventActions(EventActionCollection $eventActions): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->eventActions = $eventActions;
    }
}

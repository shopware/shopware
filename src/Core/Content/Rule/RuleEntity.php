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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\TaxProvider\TaxProviderCollection;

#[Package('business-ops')]
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
     * @internal
     *
     * @var string|Rule|null
     */
    protected $payload;

    /**
     * @var string[]|null
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
     * @var RuleConditionCollection|null
     */
    protected $conditions;

    /**
     * @var bool
     */
    protected $invalid;

    /**
     * @var string[]|null
     */
    protected ?array $areas = null;

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

    protected ?TaxProviderCollection $taxProviders = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return Rule|string|null
     */
    public function getPayload()
    {
        $this->checkIfPropertyAccessIsAllowed('payload');

        return $this->payload;
    }

    /**
     * @internal
     *
     * @param Rule|string|null $payload
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

    /**
     * @return string[]|null
     */
    public function getAreas(): ?array
    {
        return $this->areas;
    }

    /**
     * @param string[] $areas
     */
    public function setAreas(array $areas): void
    {
        $this->areas = $areas;
    }

    /**
     * @return string[]|null
     */
    public function getModuleTypes(): ?array
    {
        return $this->moduleTypes;
    }

    /**
     * @param string[]|null $moduleTypes
     */
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

    public function getTaxProviders(): ?TaxProviderCollection
    {
        return $this->taxProviders;
    }

    public function setTaxProviders(TaxProviderCollection $taxProviders): void
    {
        $this->taxProviders = $taxProviders;
    }
}

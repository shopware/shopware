<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;

class PromotionEntity extends Entity
{
    use EntityIdTrait;

    public const CODE_TYPE_NO_CODE = 'no_code';

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var \DateTimeInterface|null
     */
    protected $validFrom;

    /**
     * @var \DateTimeInterface|null
     */
    protected $validUntil;

    /**
     * @var int
     */
    protected $maxRedemptionsGlobal;

    /**
     * @var int
     */
    protected $maxRedemptionsPerCustomer;

    /**
     * @var bool
     */
    protected $exclusive;

    /**
     * @var bool
     */
    protected $useCodes;

    /**
     * @var PromotionSalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var string|null
     */
    protected $code;

    /**
     * @var PromotionDiscountCollection|null
     */
    protected $discounts;

    /**
     * @var RuleCollection|null
     */
    protected $orderRules;

    /**
     * @var RuleCollection|null
     */
    protected $personaRules;

    /**
     * @var CustomerCollection|null
     */
    protected $personaCustomers;

    /**
     * @var RuleCollection|null
     */
    protected $cartRules;

    /**
     * @var PromotionTranslationCollection|null
     */
    protected $translations;

    /**
     * @var int
     */
    protected $orderCount;

    /**
     * @var array|null
     */
    protected $ordersPerCustomerCount;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeInterface $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(\DateTimeInterface $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function getMaxRedemptionsGlobal(): int
    {
        return $this->maxRedemptionsGlobal;
    }

    public function setMaxRedemptionsGlobal(int $maxRedemptionsGlobal): void
    {
        $this->maxRedemptionsGlobal = $maxRedemptionsGlobal;
    }

    public function getMaxRedemptionsPerCustomer(): int
    {
        return $this->maxRedemptionsPerCustomer;
    }

    public function setMaxRedemptionsPerCustomer(int $maxRedemptionsPerCustomer): void
    {
        $this->maxRedemptionsPerCustomer = $maxRedemptionsPerCustomer;
    }

    public function isExclusive(): bool
    {
        return $this->exclusive;
    }

    public function setExclusive(bool $exclusive): void
    {
        $this->exclusive = $exclusive;
    }

    /**
     * Gets if the promotion requires codes
     * in order to be used
     */
    public function isUseCodes(): bool
    {
        return $this->useCodes;
    }

    /**
     * Sets if the promotion requires a code
     * to be used.
     */
    public function setUseCodes(bool $useCodes): void
    {
        $this->useCodes = $useCodes;
    }

    public function getDiscounts(): ?PromotionDiscountCollection
    {
        return $this->discounts;
    }

    public function setDiscounts(PromotionDiscountCollection $discounts): void
    {
        $this->discounts = $discounts;
    }

    /**
     * Gets a list of all assigned sales channels for this promotion.
     * Only customers within these channels are allowed
     * to use this promotion.
     */
    public function getSalesChannels(): ?PromotionSalesChannelCollection
    {
        return $this->salesChannels;
    }

    /**
     * Sets a list of permitted sales channels for this promotion.
     * Only customers within these channels are allowed to use this promotion.
     */
    public function setSalesChannels(?PromotionSalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    /**
     * Gets a list of "order" related rules that need to
     * be valid for this promotion.
     */
    public function getOrderRules(): ?RuleCollection
    {
        return $this->orderRules;
    }

    /**
     * Sets what products are affected by the applied
     * order conditions for this promotion.
     */
    public function setOrderRules(?RuleCollection $orderRules): void
    {
        $this->orderRules = $orderRules;
    }

    public function getOrderCount(): int
    {
        return $this->orderCount;
    }

    public function setOrderCount(int $orderCount): void
    {
        $this->orderCount = $orderCount;
    }

    public function getOrdersPerCustomerCount(): ?array
    {
        return $this->ordersPerCustomerCount;
    }

    public function setOrdersPerCustomerCount(array $ordersPerCustomerCount): void
    {
        $this->ordersPerCustomerCount = $ordersPerCustomerCount;
    }

    /**
     * Gets a list of "persona" related rules that need to
     * be valid for this promotion.
     */
    public function getPersonaRules(): ?RuleCollection
    {
        return $this->personaRules;
    }

    /**
     * Sets what "personas" are allowed
     * to use this promotion.
     */
    public function setPersonaRules(?RuleCollection $personaRules): void
    {
        $this->personaRules = $personaRules;
    }

    /**
     * Gets a list of all customers that have a
     * restricted access due to the explicit assignment
     * within the persona condition settings of the promotion.
     */
    public function getPersonaCustomers(): ?CustomerCollection
    {
        return $this->personaCustomers;
    }

    /**
     * Sets the customers that have explicit access to this promotion.
     * This should be configured within the persona settings of the promotion.
     */
    public function setPersonaCustomers(?CustomerCollection $customers): void
    {
        $this->personaCustomers = $customers;
    }

    /**
     * Gets a list of "cart" related rules that need to
     * be valid for this promotion.
     */
    public function getCartRules(): ?RuleCollection
    {
        return $this->cartRules;
    }

    /**
     * Sets what products are affected by the applied
     * cart conditions for this promotion.
     */
    public function setCartRules(?RuleCollection $cartRules): void
    {
        $this->cartRules = $cartRules;
    }

    public function getTranslations(): ?PromotionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(PromotionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    /**
     * Builds our aggregated precondition rule condition for this promotion.
     * If this rule matches within all its sub conditions, then the
     * whole promotion is allowed to be used.
     */
    public function getPreconditionRule(): Rule
    {
        // we combine each topics with an AND and a OR inside of their rules.
        // so all topics have to match, and one topic needs at least 1 rule that matches.
        $requirements = new AndRule(
            []
        );

        $personasFound = false;
        $personaOR = new OrRule();

        // check if we have persona rules and add them
        // to our persona OR as a separate OR rule with all configured rules
        if ($this->getPersonaRules() !== null && count($this->getPersonaRules()->getElements()) > 0) {
            $personaRuleOR = new OrRule();

            /** @var RuleEntity $ruleEntity */
            foreach ($this->getPersonaRules()->getElements() as $ruleEntity) {
                // add rule our rule and also make sure
                // we know that at least a persona exists.
                $personaRuleOR->addRule($ruleEntity->getPayload());
                $personasFound = true;
            }

            $personaOR->addRule($personaRuleOR);
        }

        // check if we have customers.
        // if so, create customer rules for it and add that also as
        // a separate OR condition to our main persona rule
        if ($this->getPersonaCustomers() !== null) {
            $personaCustomerOR = new OrRule();

            /* @var CustomerEntity $ruleEntity */
            foreach ($this->getPersonaCustomers()->getElements() as $customer) {
                // build our new rule for this
                // customer and his/her customer number
                $custRule = new CustomerNumberRule();
                $custRule->assign(['numbers' => [$customer->getCustomerNumber()], 'operator' => CustomerNumberRule::OPERATOR_EQ]);

                // add rule for customer and make sure
                // we know that at least a persona exists.
                $personaCustomerOR->addRule($custRule);
                $personasFound = true;
            }

            $personaOR->addRule($personaCustomerOR);
        }

        // if we have found any persona rule
        // or customer, then add our main persona rule
        if ($personasFound) {
            $requirements->addRule($personaOR);
        }

        if ($this->getCartRules() !== null && count($this->getCartRules()->getElements()) > 0) {
            $cartOR = new OrRule([]);

            /** @var RuleEntity $ruleEntity */
            foreach ($this->getCartRules()->getElements() as $ruleEntity) {
                $cartOR->addRule($ruleEntity->getPayload());
            }

            $requirements->addRule($cartOR);
        }

        if ($this->getOrderRules() !== null && count($this->getOrderRules()->getElements()) > 0) {
            $orderOR = new OrRule([]);

            /** @var RuleEntity $ruleEntity */
            foreach ($this->getOrderRules()->getElements() as $ruleEntity) {
                $orderOR->addRule($ruleEntity->getPayload());
            }

            $requirements->addRule($orderOR);
        }

        return $requirements;
    }

    public function isOrderCountValid(): bool
    {
        return $this->getMaxRedemptionsGlobal() <= 0
            || $this->getOrderCount() < $this->getMaxRedemptionsGlobal();
    }

    public function isOrderCountPerCustomerCountValid(string $customerId): bool
    {
        return $this->getMaxRedemptionsPerCustomer() <= 0
            || $this->getOrdersPerCustomerCount() === null
            || !array_key_exists($customerId, $this->getOrdersPerCustomerCount())
            || $this->getOrdersPerCustomerCount()[$customerId] < $this->getMaxRedemptionsPerCustomer();
    }
}

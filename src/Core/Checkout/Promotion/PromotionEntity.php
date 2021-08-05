<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Checkout\Cart\Rule\LineItemGroupRule;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;

class PromotionEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

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
     * @var int|null
     */
    protected $maxRedemptionsGlobal;

    /**
     * @var int|null
     */
    protected $maxRedemptionsPerCustomer;

    /**
     * @var bool
     */
    protected $exclusive;

    /**
     * @var bool
     */
    protected $useCodes = false;

    /**
     * @var bool
     */
    protected $useSetGroups = false;

    /**
     * stores if the persona condition uses
     * rules or customer assignments.
     * default modes is "use rules".
     *
     * @var bool
     */
    protected $customerRestriction = false;

    protected bool $preventCombination = false;

    /**
     * @var bool
     */
    protected $useIndividualCodes;

    /**
     * @var string
     */
    protected $individualCodePattern;

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
     * @var PromotionIndividualCodeCollection|null
     */
    protected $individualCodes;

    /**
     * @var PromotionSetGroupCollection|null
     */
    protected $setgroups;

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

    /**
     * @var string[]
     */
    protected $exclusionIds;

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

    public function getMaxRedemptionsGlobal(): ?int
    {
        return $this->maxRedemptionsGlobal;
    }

    public function setMaxRedemptionsGlobal(?int $maxRedemptionsGlobal): void
    {
        $this->maxRedemptionsGlobal = $maxRedemptionsGlobal;
    }

    public function getMaxRedemptionsPerCustomer(): ?int
    {
        return $this->maxRedemptionsPerCustomer;
    }

    public function setMaxRedemptionsPerCustomer(?int $maxRedemptionsPerCustomer): void
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

    public function isUseSetGroups(): bool
    {
        return $this->useSetGroups;
    }

    public function setUseSetGroups(bool $useSetGroups): void
    {
        $this->useSetGroups = $useSetGroups;
    }

    public function getSetgroups(): ?PromotionSetGroupCollection
    {
        return $this->setgroups;
    }

    public function setSetgroups(PromotionSetGroupCollection $setgroups): void
    {
        $this->setgroups = $setgroups;
    }

    /**
     * Gets if the promotion requires individual codes to be used
     */
    public function isUseIndividualCodes(): bool
    {
        return $this->useIndividualCodes;
    }

    /**
     * Sets if the promotion requires individual codes to be used.
     */
    public function setUseIndividualCodes(bool $useCodes): void
    {
        $this->useIndividualCodes = $useCodes;
    }

    /**
     * Gets the placeholder pattern that will be used
     * to generate new individual codes.
     *
     * @return string the pattern for individual code generation
     */
    public function getIndividualCodePattern(): string
    {
        return $this->individualCodePattern;
    }

    /**
     * Sets the placeholder pattern that will be used
     * to generate new individual codes.
     *
     * @param string $pattern the pattern for individual code generation
     */
    public function setIndividualCodePattern(string $pattern): void
    {
        $this->individualCodePattern = $pattern;
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
     * Gets all individual codes of the promotion,
     * if existing.
     */
    public function getIndividualCodes(): ?PromotionIndividualCodeCollection
    {
        return $this->individualCodes;
    }

    /**
     * Sets the list of individual codes
     * for this promotion.
     */
    public function setIndividualCodes(PromotionIndividualCodeCollection $individualCodes): void
    {
        $this->individualCodes = $individualCodes;
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
    public function setSalesChannels(PromotionSalesChannelCollection $salesChannels): void
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
     * Gets if the persona condition is based on
     * direct customer restrictions or on persona rules.
     */
    public function isCustomerRestriction(): bool
    {
        return $this->customerRestriction;
    }

    /**
     * Sets if the persona condition is based on
     * a direct customer restriction or on persona rules.
     */
    public function setCustomerRestriction(bool $customerRestriction): void
    {
        $this->customerRestriction = $customerRestriction;
    }

    public function isPreventCombination(): bool
    {
        return $this->preventCombination;
    }

    public function setPreventCombination(bool $preventCombination): void
    {
        $this->preventCombination = $preventCombination;
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
    public function setOrderRules(RuleCollection $orderRules): void
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
    public function setPersonaRules(RuleCollection $personaRules): void
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
    public function setPersonaCustomers(CustomerCollection $customers): void
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
    public function setCartRules(RuleCollection $cartRules): void
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

    public function getExclusionIds(): array
    {
        if ($this->exclusionIds === null) {
            return [];
        }

        return $this->exclusionIds;
    }

    public function setExclusionIds(array $exclusionIds): void
    {
        $this->exclusionIds = $exclusionIds;
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

        // first check if we either restrict our persona
        // with direct customer assignments or with persona rules
        if ($this->isCustomerRestriction()) {
            // we use assigned customers
            // check if we have customers.
            // if so, create customer rules for it and add that also as
            // a separate OR condition to our main persona rule
            if ($this->getPersonaCustomers() !== null) {
                $personaCustomerOR = new OrRule();

                foreach ($this->getPersonaCustomers()->getElements() as $customer) {
                    // build our new rule for this
                    // customer and his/her customer number
                    $custRule = new CustomerNumberRule();
                    $custRule->assign(['numbers' => [$customer->getCustomerNumber()], 'operator' => CustomerNumberRule::OPERATOR_EQ]);

                    $personaCustomerOR->addRule($custRule);
                }

                // add the rule to our main rule
                $requirements->addRule($personaCustomerOR);
            }
        } else {
            // we use persona rules.
            // check if we have persona rules and add them
            // to our persona OR as a separate OR rule with all configured rules
            if ($this->getPersonaRules() !== null && \count($this->getPersonaRules()->getElements()) > 0) {
                $personaRuleOR = new OrRule();

                foreach ($this->getPersonaRules()->getElements() as $ruleEntity) {
                    $personaRuleOR->addRule($ruleEntity->getPayload());
                }

                $requirements->addRule($personaRuleOR);
            }
        }

        if ($this->getCartRules() !== null && \count($this->getCartRules()->getElements()) > 0) {
            $cartOR = new OrRule([]);

            foreach ($this->getCartRules()->getElements() as $ruleEntity) {
                $cartOR->addRule($ruleEntity->getPayload());
            }

            $requirements->addRule($cartOR);
        }

        // verify if we are in SetGroup mode and build
        // a custom setgroup rule for all groups
        if ($this->isUseSetGroups() !== null && $this->isUseSetGroups() && $this->getSetgroups() !== null && $this->getSetgroups()->count() > 0) {
            // if we have groups, then all groups
            // must match now to fulfill the new group definition in shopware promotions
            $groupsRootRule = new AndRule();

            foreach ($this->getSetgroups() as $group) {
                $groupRule = new LineItemGroupRule();
                $groupRule->assign(
                    [
                        'groupId' => $group->getId(),
                        'packagerKey' => $group->getPackagerKey(),
                        'value' => $group->getValue(),
                        'sorterKey' => $group->getSorterKey(),
                        'rules' => $group->getSetGroupRules(),
                    ]
                );

                $groupsRootRule->addRule($groupRule);
            }

            $requirements->addRule($groupsRootRule);
        }

        if ($this->getOrderRules() !== null && \count($this->getOrderRules()->getElements()) > 0) {
            $orderOR = new OrRule([]);

            foreach ($this->getOrderRules()->getElements() as $ruleEntity) {
                $orderOR->addRule($ruleEntity->getPayload());
            }

            $requirements->addRule($orderOR);
        }

        return $requirements;
    }

    /**
     * Gets if the promotion has at least 1 discount.
     */
    public function hasDiscount(): bool
    {
        return $this->discounts instanceof PromotionDiscountCollection && $this->discounts->count() > 0;
    }

    public function isOrderCountValid(): bool
    {
        return $this->getMaxRedemptionsGlobal() === null
            || $this->getMaxRedemptionsGlobal() <= 0
            || $this->getOrderCount() < $this->getMaxRedemptionsGlobal();
    }

    public function isOrderCountPerCustomerCountValid(string $customerId): bool
    {
        $customerId = mb_strtolower($customerId);

        return $this->getMaxRedemptionsPerCustomer() === null
            || $this->getMaxRedemptionsPerCustomer() <= 0
            || $this->getOrdersPerCustomerCount() === null
            || !\array_key_exists($customerId, $this->getOrdersPerCustomerCount())
            || $this->getOrdersPerCustomerCount()[$customerId] < $this->getMaxRedemptionsPerCustomer();
    }
}

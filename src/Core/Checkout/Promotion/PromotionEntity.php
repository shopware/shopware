<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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
     * @var float
     */
    protected $value;

    /**
     * @var bool
     */
    protected $percental;

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
    protected $redeemable;

    /**
     * @var bool
     */
    protected $exclusive;

    /**
     * @var int
     */
    protected $priority;

    /**
     * @var bool
     */
    protected $excludeLowerPriority;

    /**
     * @var string|null
     */
    protected $personaRuleId;

    /**
     * @var string|null
     */
    protected $scopeRuleId;

    /**
     * @var string|null
     */
    protected $discountRuleId;

    /**
     * @var RuleEntity|null
     */
    protected $personaRule;

    /**
     * @var RuleEntity|null
     */
    protected $scopeRule;

    /**
     * @var RuleEntity|null
     */
    protected $discountRule;

    /**
     * @var string
     */
    protected $codeType;

    /**
     * @var PromotionSalesChannelCollection|null
     */
    protected $salesChannels;

    /** @var string|null */
    protected $code;

    /**
     * @var PromotionDiscountCollection|null
     */
    protected $discounts;

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

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): void
    {
        $this->value = $value;
    }

    public function isPercental(): bool
    {
        return $this->percental;
    }

    public function setPercental(bool $percental): void
    {
        $this->percental = $percental;
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

    public function getRedeemable(): int
    {
        return $this->redeemable;
    }

    public function setRedeemable(int $redeemable): void
    {
        $this->redeemable = $redeemable;
    }

    public function isExclusive(): bool
    {
        return $this->exclusive;
    }

    public function setExclusive(bool $exclusive): void
    {
        $this->exclusive = $exclusive;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function isExcludeLowerPriority(): bool
    {
        return $this->excludeLowerPriority;
    }

    public function setExcludeLowerPriority(bool $excludeLowerPriority): void
    {
        $this->excludeLowerPriority = $excludeLowerPriority;
    }

    public function getPersonaRuleId(): ?string
    {
        return $this->personaRuleId;
    }

    public function setPersonaRuleId(string $personaRuleId): void
    {
        $this->personaRuleId = $personaRuleId;
    }

    public function getScopeRuleId(): ?string
    {
        return $this->scopeRuleId;
    }

    public function setScopeRuleId(string $scopeRuleId): void
    {
        $this->scopeRuleId = $scopeRuleId;
    }

    public function getDiscountRuleId(): ?string
    {
        return $this->discountRuleId;
    }

    public function setDiscountRuleId(string $discountRuleId): void
    {
        $this->discountRuleId = $discountRuleId;
    }

    public function getPersonaRule(): ?RuleEntity
    {
        return $this->personaRule;
    }

    public function setPersonaRule(RuleEntity $personaRule): void
    {
        $this->personaRule = $personaRule;
    }

    public function getScopeRule(): ?RuleEntity
    {
        return $this->scopeRule;
    }

    public function setScopeRule(RuleEntity $scopeRule): void
    {
        $this->scopeRule = $scopeRule;
    }

    public function getDiscountRule(): ?RuleEntity
    {
        return $this->discountRule;
    }

    public function setDiscountRule(RuleEntity $discountRule): void
    {
        $this->discountRule = $discountRule;
    }

    public function getCodeType(): string
    {
        return $this->codeType;
    }

    public function setCodeType(string $codeType): void
    {
        $this->codeType = $codeType;
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
     * Gets if the promotion is valid in the current context
     * based on its Persona Rule configuration.
     */
    public function isPersonaValid(SalesChannelContext $context): bool
    {
        if ($this->getPersonaRule() === null) {
            return true;
        }

        // verify if our persona rule from our promotion
        // is part of our existing rules within the checkout context
        if (!in_array($this->getPersonaRule()->getId(), $context->getRuleIds(), true)) {
            return false;
        }

        return true;
    }

    /**
     * Gets if the promotion is valid in the current context
     * based on its Scope Rule configuration.
     */
    public function isScopeValid(SalesChannelContext $context): bool
    {
        if ($this->getScopeRule() === null) {
            return true;
        }

        // verify if our scope rule from our promotion
        // is part of our existing rules within the checkout context
        return in_array($this->getScopeRule()->getId(), $context->getRuleIds(), true);
    }
}

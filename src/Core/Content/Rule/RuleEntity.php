<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleCollection;
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
     * @var Rule
     */
    protected $payload;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var DiscountSurchargeCollection|null
     */
    protected $discountSurcharges;

    /**
     * @var ProductPriceRuleCollection|null
     */
    protected $productPriceRules;

    /**
     * @var RuleConditionCollection|null
     */
    protected $conditions;

    /**
     * @var bool
     */
    protected $inactive;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPayload(): Rule
    {
        return $this->payload;
    }

    public function setPayload(Rule $payload): void
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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
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

    public function getProductPriceRules(): ?ProductPriceRuleCollection
    {
        return $this->productPriceRules;
    }

    public function setProductPriceRules(ProductPriceRuleCollection $productPriceRules): void
    {
        $this->productPriceRules = $productPriceRules;
    }

    public function getConditions(): ?RuleConditionCollection
    {
        return $this->conditions;
    }

    public function setConditions(RuleConditionCollection $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function isInactive(): bool
    {
        return $this->inactive;
    }

    public function setInactive(bool $inactive): void
    {
        $this->inactive = $inactive;
    }
}

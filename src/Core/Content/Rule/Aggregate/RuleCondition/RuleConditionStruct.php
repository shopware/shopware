<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Aggregate\RuleCondition;

use Shopware\Core\Content\Rule\RuleStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class RuleConditionStruct extends Entity
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $ruleId;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var array|null
     */
    protected $value;

    /**
     * @var RuleStruct|null
     */
    protected $rule;

    /**
     * @var RuleConditionCollection|null
     */
    protected $children;

    /**
     * @var RuleConditionStruct|null
     */
    protected $parent;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getRuleId(): string
    {
        return $this->ruleId;
    }

    public function setRuleId(string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getValue(): ?array
    {
        return $this->value;
    }

    public function setValue(?array $value): void
    {
        $this->value = $value;
    }

    public function getRule(): ?RuleStruct
    {
        return $this->rule;
    }

    public function setRule(?RuleStruct $rule): void
    {
        $this->rule = $rule;
    }

    public function getChildren(): ?RuleConditionCollection
    {
        return $this->children;
    }

    public function setChildren(?RuleConditionCollection $children): void
    {
        $this->children = $children;
    }

    public function getParent(): ?RuleConditionStruct
    {
        return $this->parent;
    }

    public function setParent(?RuleConditionStruct $parent): void
    {
        $this->parent = $parent;
    }
}
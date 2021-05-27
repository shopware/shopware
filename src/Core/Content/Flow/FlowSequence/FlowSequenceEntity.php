<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\FlowSequence;

use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 */
class FlowSequenceEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $flowId;

    /**
     * @var FlowEntity|null
     */
    protected $flow;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var FlowSequenceEntity|null
     */
    protected $parent;

    /**
     * @var FlowSequenceCollection|null
     */
    protected $children;

    /**
     * @var string|null
     */
    protected $ruleId;

    /**
     * @var RuleEntity|null
     */
    protected $rule;

    /**
     * @var string|null
     */
    protected $actionName;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var int
     */
    protected $displayGroup;

    /**
     * @var bool
     */
    protected $trueCase;

    public function getFlowId(): string
    {
        return $this->flowId;
    }

    public function setFlowId(string $flowId): void
    {
        $this->flowId = $flowId;
    }

    public function getFlow(): ?FlowEntity
    {
        return $this->flow;
    }

    public function setFlow(FlowEntity $flow): void
    {
        $this->flow = $flow;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getParent(): ?FlowSequenceEntity
    {
        return $this->parent;
    }

    public function getChildren(): ?FlowSequenceCollection
    {
        return $this->children;
    }

    public function setChildren(FlowSequenceCollection $children): void
    {
        $this->children = $children;
    }

    public function setParent(FlowSequenceEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getRuleId(): ?string
    {
        return $this->ruleId;
    }

    public function setRuleId(string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getRule(): ?RuleEntity
    {
        return $this->rule;
    }

    public function setRule(RuleEntity $rule): void
    {
        $this->rule = $rule;
    }

    public function getActionName(): ?string
    {
        return $this->actionName;
    }

    public function setActionName(string $actionName): void
    {
        $this->actionName = $actionName;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getDisplayGroup(): int
    {
        return $this->displayGroup;
    }

    public function setDisplayGroup(int $displayGroup): void
    {
        $this->displayGroup = $displayGroup;
    }

    public function isTrueCase(): bool
    {
        return $this->trueCase;
    }

    public function setTrueCase(bool $trueCase): void
    {
        $this->trueCase = $trueCase;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Aggregate\FlowSequence;

use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class FlowSequenceEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    protected string $flowId;

    protected ?FlowEntity $flow = null;

    protected ?string $parentId = null;

    protected ?FlowSequenceEntity $parent = null;

    protected ?FlowSequenceCollection $children = null;

    protected ?string $ruleId = null;

    protected ?RuleEntity $rule = null;

    protected ?string $actionName = null;

    protected array $config;

    protected int $position;

    protected int $displayGroup;

    protected bool $trueCase;

    protected ?string $appFlowActionId = null;

    protected ?AppFlowActionEntity $appFlowAction = null;

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

    public function getAppFlowActionId(): ?string
    {
        return $this->appFlowActionId;
    }

    public function setAppFlowActionId(?string $appFlowActionId): void
    {
        $this->appFlowActionId = $appFlowActionId;
    }

    public function getAppFlowAction(): ?AppFlowActionEntity
    {
        return $this->appFlowAction;
    }

    public function setAppFlowAction(?AppFlowActionEntity $appFlowAction): void
    {
        $this->appFlowAction = $appFlowAction;
    }
}

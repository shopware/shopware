<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppScriptCondition;

use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AppScriptConditionEntity extends Entity
{
    use EntityIdTrait;

    protected string $appId;

    protected ?AppEntity $app = null;

    protected string $identifier;

    protected ?string $name = null;

    protected bool $active;

    protected ?string $group = null;

    protected ?string $script = null;

    /**
     * @internal
     *
     * @var string|array|null
     */
    protected $constraints;

    protected ?array $config;

    protected ?RuleConditionCollection $ruleConditions = null;

    protected ?AppScriptConditionTranslationCollection $translations = null;

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
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

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function setGroup(?string $group): void
    {
        $this->group = $group;
    }

    public function getScript(): ?string
    {
        return $this->script;
    }

    public function setScript(?string $script): void
    {
        $this->script = $script;
    }

    /**
     * @internal
     *
     * @return string|array|null
     */
    public function getConstraints()
    {
        $this->checkIfPropertyAccessIsAllowed('constraints');

        return $this->constraints;
    }

    /**
     * @internal
     *
     * @param string|array|null $constraints
     */
    public function setConstraints($constraints): void
    {
        $this->constraints = $constraints;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function getRuleConditions(): ?RuleConditionCollection
    {
        return $this->ruleConditions;
    }

    public function setRuleConditions(RuleConditionCollection $conditions): void
    {
        $this->ruleConditions = $conditions;
    }

    public function getTranslations(): ?AppScriptConditionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(AppScriptConditionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}

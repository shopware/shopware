<?php declare(strict_types=1);

namespace Shopware\Core\System\TaxProvider;

use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\TaxProvider\Aggregate\TaxProviderTranslation\TaxProviderTranslationCollection;

#[Package('checkout')]
class TaxProviderEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    protected ?string $name = null;

    protected bool $active;

    protected int $priority;

    protected string $identifier;

    protected ?string $availabilityRuleId = null;

    protected ?RuleEntity $availabilityRule = null;

    protected ?TaxProviderTranslationCollection $translations = null;

    protected ?string $appId = null;

    protected ?AppEntity $app = null;

    protected ?string $processUrl = null;

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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getAvailabilityRuleId(): ?string
    {
        return $this->availabilityRuleId;
    }

    public function setAvailabilityRuleId(?string $availabilityRuleId): void
    {
        $this->availabilityRuleId = $availabilityRuleId;
    }

    public function getAvailabilityRule(): ?RuleEntity
    {
        return $this->availabilityRule;
    }

    public function setAvailabilityRule(RuleEntity $availabilityRule): void
    {
        $this->availabilityRule = $availabilityRule;
    }

    public function getTranslations(): ?TaxProviderTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(TaxProviderTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function setAppId(?string $appId): void
    {
        $this->appId = $appId;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(AppEntity $app): void
    {
        $this->app = $app;
    }

    public function getProcessUrl(): ?string
    {
        return $this->processUrl;
    }

    public function setProcessUrl(?string $processUrl): void
    {
        $this->processUrl = $processUrl;
    }
}

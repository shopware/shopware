<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodCollection;
use Shopware\Core\Framework\App\Aggregate\AppTranslation\AppTranslationCollection;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Webhook\WebhookCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\Integration\IntegrationEntity;

/**
 * @internal
 */
class AppEntity extends Entity
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
    protected $name;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string|null
     */
    protected $author;

    /**
     * @var string|null
     */
    protected $copyright;

    /**
     * @var string|null
     */
    protected $license;

    /**
     * @var string|null
     */
    protected $privacy;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var array
     */
    protected $modules;

    /**
     * @var array|null
     */
    protected $mainModule;

    /**
     * @var array
     */
    protected $cookies;

    /**
     * @var string|null
     */
    protected $iconRaw;

    /**
     * @var string|null
     */
    protected $icon;

    /**
     * @var AppTranslationCollection|null
     */
    protected $translations;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $privacyPolicyExtensions;

    /**
     * @var string|null
     */
    protected $appSecret;

    /**
     * @var string
     */
    protected $integrationId;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var bool
     */
    protected $configurable;

    /**
     * @var IntegrationEntity|null
     */
    protected $integration;

    /**
     * @var string
     */
    protected $aclRoleId;

    /**
     * @var AclRoleEntity|null
     */
    protected $aclRole;

    /**
     * @var TemplateCollection|null
     */
    protected $templates;

    /**
     * @var CustomFieldSetCollection|null
     */
    protected $customFieldSets;

    /**
     * @var ActionButtonCollection|null
     */
    protected $actionButtons;

    /**
     * @var WebhookCollection|null
     */
    protected $webhooks;

    /**
     * @var AppPaymentMethodCollection|null
     */
    protected $paymentMethods;

    /**
     * @internal (flag:FEATURE_NEXT_14408)
     *
     * @var AppCmsBlockCollection|null
     */
    protected $cmsBlocks;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string the path relative to project dir
     */
    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): void
    {
        $this->author = $author;
    }

    public function getCopyright(): ?string
    {
        return $this->copyright;
    }

    public function setCopyright(?string $copyright): void
    {
        $this->copyright = $copyright;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function setLicense(?string $license): void
    {
        $this->license = $license;
    }

    public function getPrivacy(): ?string
    {
        return $this->privacy;
    }

    public function setPrivacy(?string $privacy): void
    {
        $this->privacy = $privacy;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getModules(): array
    {
        return $this->modules;
    }

    public function setModules(array $modules): void
    {
        $this->modules = $modules;
    }

    public function getMainModule(): ?array
    {
        return $this->mainModule;
    }

    public function setMainModule(array $mainModule): void
    {
        $this->mainModule = $mainModule;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function setCookies(array $cookies): void
    {
        $this->cookies = $cookies;
    }

    public function getIconRaw(): ?string
    {
        return $this->iconRaw;
    }

    public function setIconRaw(?string $iconRaw): void
    {
        $this->iconRaw = $iconRaw;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function getTranslations(): ?AppTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(AppTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function setIntegrationId(string $integrationId): void
    {
        $this->integrationId = $integrationId;
    }

    public function getIntegration(): ?IntegrationEntity
    {
        return $this->integration;
    }

    public function setIntegration(?IntegrationEntity $integration): void
    {
        $this->integration = $integration;
    }

    public function getAclRoleId(): string
    {
        return $this->aclRoleId;
    }

    public function setAclRoleId(string $aclRoleId): void
    {
        $this->aclRoleId = $aclRoleId;
    }

    public function getAclRole(): ?AclRoleEntity
    {
        return $this->aclRole;
    }

    public function setAclRole(?AclRoleEntity $aclRole): void
    {
        $this->aclRole = $aclRole;
    }

    public function getCustomFieldSets(): ?CustomFieldSetCollection
    {
        return $this->customFieldSets;
    }

    public function setCustomFieldSets(CustomFieldSetCollection $customFieldSets): void
    {
        $this->customFieldSets = $customFieldSets;
    }

    public function getAppSecret(): ?string
    {
        return $this->appSecret;
    }

    public function setAppSecret(?string $appSecret): void
    {
        $this->appSecret = $appSecret;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isConfigurable(): bool
    {
        return $this->configurable;
    }

    public function setConfigurable(bool $configurable): void
    {
        $this->configurable = $configurable;
    }

    public function getActionButtons(): ?ActionButtonCollection
    {
        return $this->actionButtons;
    }

    public function setActionButtons(ActionButtonCollection $actionButtons): void
    {
        $this->actionButtons = $actionButtons;
    }

    public function getWebhooks(): ?WebhookCollection
    {
        return $this->webhooks;
    }

    public function setWebhooks(WebhookCollection $webhooks): void
    {
        $this->webhooks = $webhooks;
    }

    public function getTemplates(): ?TemplateCollection
    {
        return $this->templates;
    }

    public function setTemplates(TemplateCollection $templates): void
    {
        $this->templates = $templates;
    }

    public function getPrivacyPolicyExtensions(): ?string
    {
        return $this->privacyPolicyExtensions;
    }

    public function setPrivacyPolicyExtensions(?string $privacyPolicyExtensions): void
    {
        $this->privacyPolicyExtensions = $privacyPolicyExtensions;
    }

    public function getPaymentMethods(): ?AppPaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(AppPaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @internal (flag:FEATURE_NEXT_14408)
     */
    public function getCmsBlocks(): ?AppCmsBlockCollection
    {
        return $this->cmsBlocks;
    }

    /**
     * @internal (flag:FEATURE_NEXT_14408)
     */
    public function setCmsBlocks(AppCmsBlockCollection $cmsBlocks): void
    {
        $this->cmsBlocks = $cmsBlocks;
    }
}

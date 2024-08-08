<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodEntity;
use Shopware\Core\Framework\App\Aggregate\AppTranslation\AppTranslationCollection;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionCollection;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventCollection;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\ScriptCollection;
use Shopware\Core\Framework\Store\InAppPurchase\InAppPurchaseCollection;
use Shopware\Core\Framework\Webhook\WebhookCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\TaxProvider\TaxProviderCollection;

/**
 * @phpstan-type Module array{name: string, label: array<string, string>, parent: string, source: string|null, position: int}
 * @phpstan-type Cookie array{snippet_name: string, snippet_description?: string, cookie: string, value?: string, expiration?: int, entries?: list<array{snippet_name: string, snippet_description?: string, cookie: string, value?: string, expiration?: int}>}
 */
#[Package('core')]
class AppEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $id;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $name;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $path;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $author;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $copyright;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $license;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $privacy;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $version;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $allowDisable;

    protected ?string $baseAppUrl = null;

    protected ?string $checkoutGatewayUrl = null;

    protected ?string $inAppPurchasesGatewayUrl = null;

    /**
     * @var list<Module>
     */
    protected array $modules;

    /**
     * @var Module|null
     */
    protected ?array $mainModule = null;

    /**
     * @var list<Cookie>
     */
    protected array $cookies;

    /**
     * @var list<string>|null
     */
    protected ?array $allowedHosts = null;

    /**
     * @internal
     */
    protected ?string $iconRaw;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $icon;

    /**
     * @var AppTranslationCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $translations;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $label;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $description;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $privacyPolicyExtensions;

    /**
     * @internal
     */
    protected ?string $appSecret = null;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $integrationId;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $active;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $configurable;

    /**
     * @var IntegrationEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $integration;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $aclRoleId;

    /**
     * @var AclRoleEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $aclRole;

    /**
     * @var TemplateCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $templates;

    /**
     * @internal
     */
    protected ?ScriptCollection $scripts = null;

    /**
     * @var CustomFieldSetCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $customFieldSets;

    /**
     * @var ActionButtonCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $actionButtons;

    /**
     * @var WebhookCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $webhooks;

    /**
     * @var AppPaymentMethodCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $paymentMethods;

    protected ?TaxProviderCollection $taxProviders = null;

    /**
     * @internal
     */
    protected ?AppScriptConditionCollection $scriptConditions;

    /**
     * @internal
     */
    protected ?AppCmsBlockCollection $cmsBlocks;

    /**
     * @var AppFlowActionCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $flowActions;

    /**
     * @var AppFlowEventCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $flowEvents;

    /**
     * @var EntityCollection<AppShippingMethodEntity>|null
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected ?EntityCollection $appShippingMethods = null;

    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be strictly typed
     */
    protected $templateLoadPriority;

    protected ?InAppPurchaseCollection $inAppPurchases = null;

    protected string $sourceType = 'local';

    /**
     * @var array<string, string|null>
     */
    protected array $sourceConfig = [];

    protected bool $selfManaged = false;

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

    public function getBaseAppUrl(): ?string
    {
        return $this->baseAppUrl;
    }

    public function setBaseAppUrl(?string $baseAppUrl): void
    {
        $this->baseAppUrl = $baseAppUrl;
    }

    public function getCheckoutGatewayUrl(): ?string
    {
        return $this->checkoutGatewayUrl;
    }

    public function setCheckoutGatewayUrl(?string $checkoutGatewayUrl): void
    {
        $this->checkoutGatewayUrl = $checkoutGatewayUrl;
    }

    public function getInAppPurchasesGatewayUrl(): ?string
    {
        return $this->inAppPurchasesGatewayUrl;
    }

    public function setInAppPurchasesGatewayUrl(?string $inAppPurchasesGatewayUrl): void
    {
        $this->inAppPurchasesGatewayUrl = $inAppPurchasesGatewayUrl;
    }

    /**
     * @return list<Module>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * @param list<Module> $modules
     */
    public function setModules(array $modules): void
    {
        $this->modules = $modules;
    }

    /**
     * @return Module|null
     */
    public function getMainModule(): ?array
    {
        return $this->mainModule;
    }

    /**
     * @param Module $mainModule
     */
    public function setMainModule(array $mainModule): void
    {
        $this->mainModule = $mainModule;
    }

    /**
     * @return list<Cookie>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * @param list<Cookie> $cookies
     */
    public function setCookies(array $cookies): void
    {
        $this->cookies = $cookies;
    }

    /**
     * @return list<string>|null
     */
    public function getAllowedHosts(): ?array
    {
        return $this->allowedHosts;
    }

    /**
     * @param list<string>|null $allowedHosts
     */
    public function setAllowedHosts(?array $allowedHosts): void
    {
        $this->allowedHosts = $allowedHosts;
    }

    /**
     * @internal
     */
    public function getIconRaw(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('iconRaw');

        return $this->iconRaw;
    }

    /**
     * @internal
     */
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

    /**
     * @internal
     */
    public function getAppSecret(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('appSecret');

        return $this->appSecret;
    }

    /**
     * @internal
     */
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

    /**
     * @internal
     */
    public function getScripts(): ?ScriptCollection
    {
        $this->checkIfPropertyAccessIsAllowed('scripts');

        return $this->scripts;
    }

    /**
     * @internal
     */
    public function setScripts(ScriptCollection $scripts): void
    {
        $this->scripts = $scripts;
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

    public function getTaxProviders(): ?TaxProviderCollection
    {
        return $this->taxProviders;
    }

    public function setTaxProviders(TaxProviderCollection $taxProviders): void
    {
        $this->taxProviders = $taxProviders;
    }

    /**
     * @internal
     */
    public function getScriptConditions(): ?AppScriptConditionCollection
    {
        $this->checkIfPropertyAccessIsAllowed('scriptConditions');

        return $this->scriptConditions;
    }

    /**
     * @internal
     */
    public function setScriptConditions(AppScriptConditionCollection $scriptConditions): void
    {
        $this->scriptConditions = $scriptConditions;
    }

    /**
     * @internal
     */
    public function getCmsBlocks(): ?AppCmsBlockCollection
    {
        return $this->cmsBlocks;
    }

    /**
     * @internal
     */
    public function setCmsBlocks(AppCmsBlockCollection $cmsBlocks): void
    {
        $this->cmsBlocks = $cmsBlocks;
    }

    public function getFlowActions(): ?AppFlowActionCollection
    {
        return $this->flowActions;
    }

    public function setFlowActions(AppFlowActionCollection $flowActions): void
    {
        $this->flowActions = $flowActions;
    }

    public function getFlowEvents(): ?AppFlowEventCollection
    {
        return $this->flowEvents;
    }

    public function setFlowEvents(AppFlowEventCollection $flowEvents): void
    {
        $this->flowEvents = $flowEvents;
    }

    /**
     * @return EntityCollection<AppShippingMethodEntity>|null
     */
    public function getAppShippingMethods(): ?EntityCollection
    {
        return $this->appShippingMethods;
    }

    /**
     * @param EntityCollection<AppShippingMethodEntity> $appShippingMethods
     */
    public function setAppShippingMethods(EntityCollection $appShippingMethods): void
    {
        $this->appShippingMethods = $appShippingMethods;
    }

    public function jsonSerialize(): array
    {
        $serializedData = parent::jsonSerialize();
        unset($serializedData['iconRaw']);

        return $serializedData;
    }

    public function getAllowDisable(): bool
    {
        return $this->allowDisable;
    }

    public function setAllowDisable(bool $allowDisable): void
    {
        $this->allowDisable = $allowDisable;
    }

    public function getTemplateLoadPriority(): int
    {
        return $this->templateLoadPriority;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setTemplateLoadPriority(int $templateLoadPriority): void
    {
        $this->templateLoadPriority = $templateLoadPriority;
    }

    public function getInAppPurchases(): ?InAppPurchaseCollection
    {
        return $this->inAppPurchases;
    }

    public function setInAppPurchases(InAppPurchaseCollection $inAppPurchases): void
    {
        $this->inAppPurchases = $inAppPurchases;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): void
    {
        $this->sourceType = $sourceType;
    }

    /**
     * @return array<string, string|null>
     */
    public function getSourceConfig(): array
    {
        return $this->sourceConfig;
    }

    /**
     * @param array<string, string|null> $config
     */
    public function setSourceConfig(array $config): void
    {
        $this->sourceConfig = $config;
    }

    /**
     * Is this App managed by itself?
     *
     * If so, it should not be presented to the client, it is managed and updated by itself
     */
    public function isSelfManaged(): bool
    {
        return $this->selfManaged;
    }

    public function setSelfManaged(bool $selfManaged): void
    {
        $this->selfManaged = $selfManaged;
    }
}

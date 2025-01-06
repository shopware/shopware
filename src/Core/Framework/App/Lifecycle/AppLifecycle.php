<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Composer\Semver\VersionParser;
use Doctrine\DBAL\Connection;
use Shopware\Administration\Snippet\AppAdministrationSnippetPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Cms\CmsExtensions as CmsManifest;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\Hooks\AppDeletedHook;
use Shopware\Core\Framework\App\Event\Hooks\AppInstalledHook;
use Shopware\Core\Framework\App\Event\Hooks\AppUpdatedHook;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Flow\Action\Action;
use Shopware\Core\Framework\App\Flow\Event\Event;
use Shopware\Core\Framework\App\Lifecycle\Persister\ActionButtonPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\CmsBlockPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowActionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\FlowEventPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\RuleConditionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\ScriptPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\TaxProviderPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\TemplatePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Module;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhook;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\App\Validation\ConfigValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomEntity\CustomEntityCollection;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Xml\Field\AssociationField;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('core')]
class AppLifecycle extends AbstractAppLifecycle
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     * @param EntityRepository<AclRoleCollection> $aclRoleRepository
     * @param EntityRepository<CustomEntityCollection> $customEntityRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly PermissionPersister $permissionPersister,
        private readonly CustomFieldPersister $customFieldPersister,
        private readonly ActionButtonPersister $actionButtonPersister,
        private readonly TemplatePersister $templatePersister,
        private readonly ScriptPersister $scriptPersister,
        private readonly WebhookPersister $webhookPersister,
        private readonly PaymentMethodPersister $paymentMethodPersister,
        private readonly TaxProviderPersister $taxProviderPersister,
        private readonly RuleConditionPersister $ruleConditionPersister,
        private readonly CmsBlockPersister $cmsBlockPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AppRegistrationService $registrationService,
        private readonly AppStateService $appStateService,
        private readonly EntityRepository $languageRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly ConfigValidator $configValidator,
        private readonly EntityRepository $integrationRepository,
        private readonly EntityRepository $aclRoleRepository,
        private readonly AssetService $assetService,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly string $projectDir,
        private readonly Connection $connection,
        private readonly FlowActionPersister $flowBuilderActionPersister,
        private readonly ?AppAdministrationSnippetPersister $appAdministrationSnippetPersister,
        private readonly CustomEntitySchemaUpdater $customEntitySchemaUpdater,
        private readonly CustomEntityLifecycleService $customEntityLifecycleService,
        private readonly string $shopwareVersion,
        private readonly FlowEventPersister $flowEventPersister,
        private readonly string $env,
        private readonly ShippingMethodPersister $shippingMethodPersister,
        private readonly EntityRepository $customEntityRepository,
        private readonly SourceResolver $sourceResolver,
        private readonly ConfigReader $configReader
    ) {
    }

    public function getDecorated(): AbstractAppLifecycle
    {
        throw new DecorationPatternException(self::class);
    }

    public function install(Manifest $manifest, bool $activate, Context $context): void
    {
        $this->ensureIsCompatible($manifest);

        $app = $this->loadAppByName($manifest->getMetadata()->getName(), $context);
        if ($app) {
            throw AppException::alreadyInstalled($manifest->getMetadata()->getName());
        }

        $defaultLocale = $this->getDefaultLocale($context);
        $metadata = $manifest->getMetadata()->toArray($defaultLocale);
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $metadata = $this->enrichInstallMetadata($manifest, $metadata, $roleId);

        $app = $this->updateApp($manifest, $metadata, $appId, $roleId, $defaultLocale, $context, true);

        $event = new AppInstalledEvent($app, $manifest, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppInstalledHook($event));

        if ($activate) {
            $this->appStateService->activateApp($appId, $context);
        }

        $this->updateAclRole($app->getName(), $context);
    }

    public function update(Manifest $manifest, array $app, Context $context): void
    {
        $this->ensureIsCompatible($manifest);

        $defaultLocale = $this->getDefaultLocale($context);
        $metadata = $manifest->getMetadata()->toArray($defaultLocale);
        $appEntity = $this->updateApp($manifest, $metadata, $app['id'], $app['roleId'], $defaultLocale, $context, false);

        $event = new AppUpdatedEvent($appEntity, $manifest, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppUpdatedHook($event));
    }

    public function delete(string $appName, array $app, Context $context, bool $keepUserData = false): void
    {
        $appEntity = $this->loadApp($app['id'], $context);

        if ($appEntity->isActive()) {
            $this->appStateService->deactivateApp($appEntity->getId(), $context);
        }

        $this->removeAppAndRole($appEntity, $context, $keepUserData, true);
        $this->assetService->removeAssets($appEntity->getName());
        $this->customEntitySchemaUpdater->update();
    }

    public function ensureIsCompatible(Manifest $manifest): void
    {
        $versionParser = new VersionParser();
        if (!$manifest->getMetadata()->getCompatibility()->matches($versionParser->parseConstraints($this->shopwareVersion))) {
            throw AppException::notCompatible($manifest->getMetadata()->getName());
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function updateApp(
        Manifest $manifest,
        array $metadata,
        string $id,
        string $roleId,
        string $defaultLocale,
        Context $context,
        bool $install
    ): AppEntity {
        // accessToken is not set on update, but in that case we don't run registration, so we won't need it
        $secretAccessKey = $metadata['accessToken'] ?? '';
        unset($metadata['accessToken'], $metadata['icon']);
        $metadata['path'] = str_replace($this->projectDir . '/', '', $manifest->getPath());
        $metadata['id'] = $id;
        $metadata['modules'] = [];
        $metadata['iconRaw'] = $this->getIcon($manifest);
        $metadata['cookies'] = $manifest->getCookies() !== null ? $manifest->getCookies()->getCookies() : [];
        $metadata['baseAppUrl'] = $manifest->getAdmin()?->getBaseAppUrl();
        $metadata['allowedHosts'] = $manifest->getAllHosts();
        $metadata['templateLoadPriority'] = $manifest->getStorefront() ? $manifest->getStorefront()->getTemplateLoadPriority() : 0;
        $metadata['checkoutGatewayUrl'] = $manifest->getGateways()?->getCheckout()?->getUrl();
        $metadata['sourceType'] = $manifest->getSourceType() ?? $this->sourceResolver->resolveSourceType($manifest);
        $metadata['sourceConfig'] = $manifest->getSourceConfig();
        $metadata['inAppPurchasesGatewayUrl'] = $manifest->getGateways()?->getInAppPurchasesGateway()?->getUrl();

        $this->updateMetadata($metadata, $context);

        $app = $this->loadApp($id, $context);

        $this->updateCustomEntities($app, $manifest);

        $this->permissionPersister->updatePrivileges($manifest->getPermissions(), $roleId);

        // If the app has no secret yet, but now specifies setup data we do a registration to get an app secret
        // this mostly happens during install, but may happen in the update case if the app previously worked without an external server
        if (!$app->getAppSecret() && $manifest->getSetup()) {
            try {
                $this->registrationService->registerApp($manifest, $id, $secretAccessKey, $context);
            } catch (AppRegistrationException $e) {
                $this->removeAppAndRole($app, $context);

                throw $e;
            }
        }

        // Refetch app to get secret after registration
        $app = $this->loadApp($id, $context);

        try {
            $this->assertAppSecretIsPresentForApplicableFeatures($app, $manifest);
        } catch (AppException $e) {
            $this->removeAppAndRole($app, $context);

            throw $e;
        }

        $flowActions = $this->getFlowActions($app);

        if ($flowActions) {
            $this->flowBuilderActionPersister->updateActions($app, $flowActions, $context, $defaultLocale);
        }

        $webhooks = $this->getWebhooks($manifest, $flowActions, $id, $defaultLocale, (bool) $app->getAppSecret());
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($webhooks, $id): void {
            $this->webhookPersister->updateWebhooksFromArray($webhooks, $id, $context);
        });

        $flowEvents = $this->getFlowEvents($app);

        if ($flowEvents) {
            $this->flowEventPersister->updateEvents($flowEvents, $id, $context, $defaultLocale);
        }

        // we need an app secret to securely communicate with apps
        // therefore we only install webhooks, modules, tax providers and payment methods if we have a secret
        if ($app->getAppSecret()) {
            $this->paymentMethodPersister->updatePaymentMethods($manifest, $id, $defaultLocale, $context);
            $this->taxProviderPersister->updateTaxProviders($manifest, $id, $defaultLocale, $context);

            $this->updateModules($manifest, $id, $defaultLocale, $context);
        }

        $this->shippingMethodPersister->updateShippingMethods($manifest, $id, $defaultLocale, $context);

        $this->ruleConditionPersister->updateConditions($manifest, $id, $defaultLocale, $context);
        $this->actionButtonPersister->updateActions($manifest, $id, $defaultLocale, $context);
        $this->templatePersister->updateTemplates($manifest, $id, $context);
        $this->scriptPersister->updateScripts($id, $context);
        $this->customFieldPersister->updateCustomFields($manifest, $id, $context);
        $this->assetService->copyAssetsFromApp($app->getName(), $app->getPath());

        $cmsExtensions = $this->getCmsExtensions($app);

        if ($cmsExtensions) {
            $this->cmsBlockPersister->updateCmsBlocks($cmsExtensions, $id, $defaultLocale, $context);
        }

        $updatePayload = [
            'id' => $app->getId(),
            'configurable' => $this->handleConfigUpdates($app, $manifest, $install, $context),
            'allowDisable' => $this->doesAllowDisabling($app, $context),
        ];
        $this->updateMetadata($updatePayload, $context);

        // updates the snippets if the administration bundle is available
        if ($this->appAdministrationSnippetPersister !== null) {
            $snippets = $this->getSnippets($app);
            $this->appAdministrationSnippetPersister->updateSnippets($app, $snippets, $context);
        }

        return $app;
    }

    private function getCmsExtensions(AppEntity $app): ?CmsManifest
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has('Resources/cms.xml')) {
            return null;
        }

        return CmsManifest::createFromXmlFile($fs->path('Resources/cms.xml'));
    }

    private function getFlowEvents(AppEntity $app): ?Event
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has('Resources/flow.xml')) {
            return null;
        }

        return Event::createFromXmlFile($fs->path('Resources/flow.xml'));
    }

    private function getFlowActions(AppEntity $app): ?Action
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has('Resources/flow.xml')) {
            return null;
        }

        return Action::createFromXmlFile($fs->path('Resources/flow.xml'));
    }

    /**
     * @return array<string, string>
     */
    private function getSnippets(AppEntity $app): array
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has('Resources/app/administration/snippet')) {
            return [];
        }

        $snippets = [];
        foreach ($fs->findFiles('*.json', 'Resources/app/administration/snippet') as $file) {
            $snippets[$file->getFilenameWithoutExtension()] = $file->getContents();
        }

        return $snippets;
    }

    /**
     * @return array<array<string, mixed>>|null
     */
    private function getAppConfig(AppEntity $app): ?array
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has('Resources/config/config.xml')) {
            return null;
        }

        return $this->configReader->read($fs->path('Resources/config/config.xml'));
    }

    private function removeAppAndRole(AppEntity $app, Context $context, bool $keepUserData = false, bool $softDelete = false): void
    {
        // throw event before deleting app from db as it may be delivered via webhook to the deleted app
        $event = new AppDeletedEvent($app->getId(), $context, $keepUserData);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppDeletedHook($event));

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($app, $softDelete, $keepUserData): void {
            if (!$keepUserData) {
                $config = $this->getAppConfig($app);

                if ($config) {
                    $this->systemConfigService->deleteExtensionConfiguration($app->getName(), $config);
                }
            }

            $this->markCustomEntitiesAsDeleted($app->getId(), $keepUserData, $context);

            $this->appRepository->delete([['id' => $app->getId()]], $context);

            if ($softDelete) {
                $this->integrationRepository->update([[
                    'id' => $app->getIntegrationId(),
                    'deletedAt' => new \DateTimeImmutable(),
                ]], $context);
                $this->permissionPersister->softDeleteRole($app->getAclRoleId());
            } else {
                $this->integrationRepository->delete([['id' => $app->getIntegrationId()]], $context);
                $this->permissionPersister->removeRole($app->getAclRoleId());
            }

            $this->deleteAclRole($app->getName(), $context);
        });
    }

    private function markCustomEntitiesAsDeleted(string $appId, bool $keepUserData, Context $context): void
    {
        if (!$keepUserData) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        $customEntities = $this->customEntityRepository->search($criteria, $context)->getEntities();

        $update = [];
        foreach ($customEntities as $customEntity) {
            $update[] = [
                'id' => $customEntity->getId(),
                'appId' => null,
                'deletedAt' => new \DateTimeImmutable(),
            ];
        }

        if (empty($update)) {
            return;
        }

        $this->customEntityRepository->update($update, $context);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function updateMetadata(array $metadata, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($metadata): void {
            $this->appRepository->upsert([$metadata], $context);
        });
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function enrichInstallMetadata(Manifest $manifest, array $metadata, string $roleId): array
    {
        $secret = AccessKeyHelper::generateSecretAccessKey();

        $metadata['integration'] = [
            'label' => $manifest->getMetadata()->getName(),
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => $secret,
            'admin' => false,
        ];
        $metadata['aclRole'] = [
            'id' => $roleId,
            'name' => $manifest->getMetadata()->getName(),
        ];
        $metadata['accessToken'] = $secret;
        // Always install as inactive, activation will be handled by `AppStateService` in `install()` method.
        $metadata['active'] = false;

        return $metadata;
    }

    private function loadApp(string $id, Context $context): AppEntity
    {
        $app = $this->appRepository->search(new Criteria([$id]), $context)->getEntities()->first();
        \assert($app !== null);

        return $app;
    }

    private function loadAppByName(string $name, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        return $this->appRepository->search($criteria, $context)->getEntities()->first();
    }

    private function updateModules(Manifest $manifest, string $id, string $defaultLocale, Context $context): void
    {
        $payload = [
            'id' => $id,
            'mainModule' => null,
            'modules' => [],
        ];

        if ($manifest->getAdmin() !== null) {
            if ($manifest->getAdmin()->getMainModule() !== null) {
                $payload['mainModule'] = [
                    'source' => $manifest->getAdmin()->getMainModule()->getSource(),
                ];
            }

            $payload['modules'] = array_reduce(
                $manifest->getAdmin()->getModules(),
                static function (array $modules, Module $module) use ($defaultLocale) {
                    $modules[] = $module->toArray($defaultLocale);

                    return $modules;
                },
                []
            );
        }

        $this->appRepository->update([$payload], $context);
    }

    private function getDefaultLocale(Context $context): string
    {
        $criteria = new Criteria([Defaults::LANGUAGE_SYSTEM]);
        $criteria->addAssociation('locale');

        $language = $this->languageRepository->search($criteria, $context)->getEntities()->first();
        $locale = $language?->getLocale();
        \assert($locale !== null);

        return $locale->getCode();
    }

    private function updateAclRole(string $appName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [new EqualsFilter('users.id', null)]
        ));
        $roles = $this->aclRoleRepository->search($criteria, $context)->getEntities();

        $newPrivileges = [
            'app.' . $appName,
        ];
        $dataUpdate = [];

        foreach ($roles as $role) {
            $currentPrivileges = $role->getPrivileges();

            if (\in_array('app.all', $currentPrivileges, true)) {
                $currentPrivileges = array_merge($currentPrivileges, $newPrivileges);
                $currentPrivileges = array_unique($currentPrivileges);

                $dataUpdate[] = [
                    'id' => $role->getId(),
                    'privileges' => $currentPrivileges,
                ];
            }
        }

        if (\count($dataUpdate) > 0) {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($dataUpdate): void {
                $this->aclRoleRepository->update($dataUpdate, $context);
            });
        }
    }

    private function deleteAclRole(string $appName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('app.id', null));
        $roles = $this->aclRoleRepository->search($criteria, $context)->getEntities();

        $appPrivileges = 'app.' . $appName;
        $dataUpdate = [];

        foreach ($roles as $role) {
            $currentPrivileges = $role->getPrivileges();

            if (($key = array_search($appPrivileges, $currentPrivileges, true)) !== false) {
                unset($currentPrivileges[$key]);

                $dataUpdate[] = [
                    'id' => $role->getId(),
                    'privileges' => $currentPrivileges,
                ];
            }
        }

        if (\count($dataUpdate) > 0) {
            $this->aclRoleRepository->update($dataUpdate, $context);
        }
    }

    private function updateCustomEntities(AppEntity $app, Manifest $manifest): void
    {
        $entities = $this->customEntityLifecycleService->updateApp($app)?->getEntities()?->getEntities();

        foreach ($entities ?? [] as $entity) {
            $manifest->addPermissions([
                $entity->getName() => [
                    AclRoleDefinition::PRIVILEGE_READ,
                    AclRoleDefinition::PRIVILEGE_CREATE,
                    AclRoleDefinition::PRIVILEGE_UPDATE,
                    AclRoleDefinition::PRIVILEGE_DELETE,
                ],
            ]);
        }
    }

    private function handleConfigUpdates(AppEntity $app, Manifest $manifest, bool $install, Context $context): bool
    {
        $config = $this->getAppConfig($app);

        if ($config === null) {
            return false;
        }

        $errors = $this->configValidator->validate($manifest, null);
        $configError = $errors->first();

        if ($configError) {
            // only one error can be in the returned collection
            throw AppException::invalidConfiguration($manifest->getMetadata()->getName(), $configError);
        }

        $this->systemConfigService->saveConfig($config, $app->getName() . '.config.', $install);

        return true;
    }

    private function doesAllowDisabling(AppEntity $app, Context $context): bool
    {
        $allow = true;

        $entities = $this->connection->fetchFirstColumn(
            'SELECT fields FROM custom_entity WHERE app_id = :id',
            ['id' => Uuid::fromHexToBytes($app->getId())]
        );

        foreach ($entities as $fields) {
            $fields = json_decode((string) $fields, true, 512, \JSON_THROW_ON_ERROR);

            foreach ($fields as $field) {
                $restricted = $field['onDelete'] ?? null;

                $allow = $restricted === AssociationField::RESTRICT ? false : $allow;
            }
        }

        return $allow;
    }

    /**
     * @return array<array{name: string, eventName: string, url: string, appId: string, active?: bool, errorCount?: int}>
     */
    private function getWebhooks(Manifest $manifest, ?Action $flowActions, string $appId, string $defaultLocale, bool $hasAppSecret): array
    {
        $actions = [];

        if ($flowActions) {
            $actions = $flowActions->getActions()?->getActions() ?? [];
        }

        $webhooks = array_map(function ($action) use ($appId) {
            $name = $action->getMeta()->getName();

            return [
                'name' => $name,
                'eventName' => $name,
                'url' => $action->getMeta()->getUrl(),
                'appId' => $appId,
                'active' => true,
                'errorCount' => 0,
            ];
        }, $actions);

        if (!$hasAppSecret) {
            return $webhooks;
        }

        $manifestWebhooks = $manifest->getWebhooks()?->getWebhooks() ?? [];
        $webhooks = array_merge($webhooks, array_map(function (Webhook $webhook) use ($defaultLocale, $appId) {
            /** @var array{name: string, event: string, url: string} $payload */
            $payload = $webhook->toArray($defaultLocale);
            $payload['appId'] = $appId;
            $payload['eventName'] = $webhook->getEvent();

            return $payload;
        }, $manifestWebhooks));

        return $webhooks;
    }

    private function getIcon(Manifest $manifest): ?string
    {
        if (!$iconPath = $manifest->getMetadata()->getIcon()) {
            return null;
        }

        $fs = $this->sourceResolver->filesystemForManifest($manifest);

        return $fs->has($iconPath) ? $fs->read($iconPath) : null;
    }

    /**
     * Certain app features require an app secret to be set, if these features are used but no app secret
     * is set, we throw an exception in dev mode so the developer is aware
     */
    private function assertAppSecretIsPresentForApplicableFeatures(AppEntity $app, Manifest $manifest): void
    {
        if ($app->getAppSecret()) {
            return;
        }

        if ($this->env !== 'dev') {
            return;
        }

        $usedFeatures = [];

        if (\count($manifest->getAdmin()?->getModules() ?? []) > 0) {
            // if there is no app secret but the manifest specifies modules, throw an exception in dev mode
            $usedFeatures[] = 'Admin Modules';
        }

        if (\count($manifest->getPayments()?->getPaymentMethods() ?? []) > 0) {
            $usedFeatures[] = 'Payment Methods';
        }

        if (\count($manifest->getTax()?->getTaxProviders() ?? []) > 0) {
            $usedFeatures[] = 'Tax providers';
        }

        if (\count($manifest->getWebhooks()?->getWebhooks() ?? []) > 0) {
            $usedFeatures[] = 'Webhooks';
        }

        if (\count($usedFeatures) > 0) {
            throw AppException::appSecretRequiredForFeatures($app->getName(), $usedFeatures);
        }
    }
}

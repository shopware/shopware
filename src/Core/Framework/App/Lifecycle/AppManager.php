<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Composer\Semver\VersionParser;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\Hooks\AppActivatedHook;
use Shopware\Core\Framework\App\Event\Hooks\AppDeactivatedHook;
use Shopware\Core\Framework\App\Event\Hooks\AppDeletedHook;
use Shopware\Core\Framework\App\Event\Hooks\AppInstalledHook;
use Shopware\Core\Framework\App\Event\Hooks\AppUpdatedHook;
use Shopware\Core\Framework\App\Event\PostAppDeletedEvent;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Lifecycle\Persister\PersisterInterface;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\App\Validation\AppRequirementsValidator;
use Shopware\Core\Framework\App\Validation\ConfigValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class AppManager
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     * @param EntityRepository<AclRoleCollection> $aclRoleRepository
     * @param iterable<PersisterInterface> $persisters
     */
    public function __construct(
        private readonly iterable $persisters,
        private readonly EntityRepository $appRepository,
        private readonly PermissionLifecycleService $permissionLifecycle,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AppRegistrationService $registrationService,
        private readonly ActiveAppsLoader $activeAppsLoader,
        private readonly EntityRepository $languageRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly ConfigValidator $configValidator,
        private readonly EntityRepository $integrationRepository,
        private readonly EntityRepository $aclRoleRepository,
        private readonly AssetService $assetService,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly string $projectDir,
        private readonly CustomEntityLifecycleService $customEntityLifecycleService,
        private readonly string $shopwareVersion,
        private readonly AppFeatureValidator $appFeatureValidator,
        private readonly SourceResolver $sourceResolver,
        private readonly ConfigReader $configReader,
        private readonly DeletedAppsGateway $deletedAppsGateway,
        private readonly AppRequirementsValidator $requirementsValidator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function install(Manifest $manifest, AppInstallParameters $parameters, Context $context): void
    {
        $this->ensureIsCompatible($manifest);
        $this->ensureMeetsRequirements($manifest);

        $app = $this->loadAppByName($manifest->getMetadata()->getName(), $context);
        if ($app) {
            throw AppException::alreadyInstalled($manifest->getMetadata()->getName());
        }

        $defaultLocale = $this->getDefaultLocale($context);
        $metadata = $manifest->getMetadata()->toArray($defaultLocale);
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $metadata = $this->enrichInstallMetadata($manifest, $metadata, $roleId);

        $app = $this->persistApp(
            $manifest,
            new AppUpdateParameters(acceptPermissions: $parameters->acceptPermissions),
            $metadata,
            $appId,
            $defaultLocale,
            $context,
            true
        );

        $event = new AppInstalledEvent($app, $manifest, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppInstalledHook($event));

        if ($parameters->activate) {
            $this->activate($app, $context);
        }

        $this->updateAclRole($app->getName(), $context);
    }

    public function update(Manifest $manifest, AppUpdateParameters $parameters, AppEntity $app, Context $context): void
    {
        $this->ensureIsCompatible($manifest);
        $this->ensureMeetsRequirements($manifest);

        $defaultLocale = $this->getDefaultLocale($context);
        $metadata = $manifest->getMetadata()->toArray($defaultLocale);
        $appEntity = $this->persistApp(
            $manifest,
            $parameters,
            $metadata,
            $app->getId(),
            $defaultLocale,
            $context,
            false
        );

        $event = new AppUpdatedEvent($appEntity, $manifest, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppUpdatedHook($event));
    }

    public function delete(AppEntity $app, Context $context, bool $keepUserData = false): void
    {
        $canRemoveAppData = !$keepUserData || $this->customEntityLifecycleService->canRemoveAppData($app);
        if ($app->isActive()) {
            $this->deactivate($app, $context, $canRemoveAppData);
        }

        $this->removeAppData($app, $context, $keepUserData, true);
        $this->assetService->removeAssets($app->getName());

        $event = new PostAppDeletedEvent($app->getName(), $app->getSourceType(), $context, $keepUserData);
        $this->eventDispatcher->dispatch($event);
    }

    public function activate(AppEntity $app, Context $context): void
    {
        if ($app->isActive()) {
            return;
        }

        $this->appRepository->update([['id' => $app->getId(), 'active' => true]], $context);
        // manually set active flag to true, so we don't need to re-fetch the app from DB
        $app->setActive(true);
        $this->runPersisters(static fn (PersisterInterface $persister) => $persister->activate($app, $context));

        $this->activeAppsLoader->reset();

        $event = new AppActivatedEvent($app, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppActivatedHook($event));
    }

    public function deactivate(AppEntity $app, Context $context, bool $deactivateForDeletion = false): void
    {
        if (!$app->isActive()) {
            return;
        }
        if (!$deactivateForDeletion && !$app->getAllowDisable()) {
            throw AppException::restrictDeletePreventsDeactivation($app->getName());
        }

        // throw event before deactivating app in db as theme configs from the app need to be removed beforehand
        $event = new AppDeactivatedEvent($app, $context);
        $this->eventDispatcher->dispatch($event);
        $this->scriptExecutor->execute(new AppDeactivatedHook($event));

        $this->appRepository->update([['id' => $app->getId(), 'active' => false]], $context);
        $app->setActive(false);
        $this->runPersisters(static fn (PersisterInterface $persister) => $persister->deactivate($app, $context));

        // reset only after new state is in the DB
        $this->activeAppsLoader->reset();
    }

    private function ensureIsCompatible(Manifest $manifest): void
    {
        $versionParser = new VersionParser();
        if (!$manifest->getMetadata()->getCompatibility()->matches($versionParser->parseConstraints($this->shopwareVersion))) {
            throw AppException::notCompatible($manifest->getMetadata()->getName());
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function persistApp(
        Manifest $manifest,
        AppUpdateParameters $parameters,
        array $metadata,
        string $id,
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
        $metadata['contextGatewayUrl'] = $manifest->getGateways()?->getContext()?->getUrl();
        $metadata['sourceType'] = $manifest->getSourceType() ?? $this->sourceResolver->resolveSourceType($manifest);
        $metadata['sourceConfig'] = $manifest->getSourceConfig();
        $metadata['inAppPurchasesGatewayUrl'] = $manifest->getGateways()?->getInAppPurchasesGateway()?->getUrl();

        $this->updateMetadata($metadata, $context);

        $app = $this->loadApp($id, $context);

        $this->updateCustomEntities($app, $manifest);

        $this->permissionLifecycle->updatePrivileges(
            $manifest->getPermissions(),
            $id,
            $manifest->validatesPermissions() === false && $parameters->acceptPermissions,
            $context
        );

        // If the app has no secret yet, but now specifies setup data we do a registration to get an app secret
        // this mostly happens during install, but may happen in the update case if the app previously worked without an external server
        // additionally during install it might happen that we still have an old secret stored for the app from a previous installation
        // in that case we still need to run the registration to rotate that secret
        if ((!$app->getAppSecret() || $install) && $manifest->getSetup()) {
            try {
                $this->registrationService->registerApp($manifest, $id, $secretAccessKey, $context);
            } catch (AppRegistrationException $e) {
                $this->removeAppData($app, $context);

                throw $e;
            }
        }

        // Refetch app to get secret after registration
        $app = $this->loadApp($id, $context);

        try {
            $this->appFeatureValidator->validate($app, $manifest);
        } catch (AppException $e) {
            $this->removeAppData($app, $context);

            throw $e;
        }

        $appLifecycleContext = new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: $context,
            appFilesystem: $this->sourceResolver->filesystemForManifest($manifest),
            defaultLocale: $defaultLocale,
            isInstall: $install,
        );

        $this->runPersisters(static fn (PersisterInterface $persister) => $persister->persist($appLifecycleContext));

        $this->assetService->copyAssetsFromApp($app->getName(), $app->getPath());

        $updatePayload = [
            'id' => $app->getId(),
            'configurable' => $this->handleConfigUpdates($app, $manifest, $install),
            'allowDisable' => $this->customEntityLifecycleService->allowsDisabling($app),
        ];
        $this->updateMetadata($updatePayload, $context);

        return $app;
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

    private function removeAppData(AppEntity $app, Context $context, bool $keepUserData = false, bool $softDelete = false): void
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

            $this->customEntityLifecycleService->removeApp($app, $context, $keepUserData);

            $this->appRepository->delete([['id' => $app->getId()]], $context);

            if ($softDelete) {
                $this->integrationRepository->update([[
                    'id' => $app->getIntegrationId(),
                    'deletedAt' => $this->clock->now(),
                ]], $context);
                $this->permissionLifecycle->softDeleteRole($app->getAclRoleId());
            } else {
                $this->integrationRepository->delete([['id' => $app->getIntegrationId()]], $context);
                $this->permissionLifecycle->removeRole($app->getAclRoleId());
            }

            $this->deleteAclRole($app->getName(), $context);
        });
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
        $appName = $manifest->getMetadata()->getName();

        $metadata['integration'] = [
            'label' => $appName,
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => $secret,
            'admin' => false,
        ];

        $metadata['aclRole'] = [
            'id' => $roleId,
            'name' => $appName,
        ];
        $metadata['accessToken'] = $secret;
        // Always install as inactive, activation will be handled by `AppManager` in `install()` method.
        $metadata['active'] = false;
        // when the app was installed before and we have the old secret stored, we set it here
        // so the registration is signed correctly with the old secret
        $oldSecret = $this->deletedAppsGateway->getDeletedAppSecret($appName);
        if ($oldSecret !== null) {
            $metadata['appSecret'] = $oldSecret;
        }

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

    private function getDefaultLocale(Context $context): string
    {
        $criteria = new Criteria([Defaults::LANGUAGE_SYSTEM]);
        $criteria->addAssociation('translationCode');

        $language = $this->languageRepository->search($criteria, $context)->getEntities()->first();
        $locale = $language?->getTranslationCode();
        \assert($locale !== null);

        return $locale->getCode();
    }

    private function updateAclRole(string $appName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('users.id', null));
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

        if ($dataUpdate !== []) {
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

        if ($dataUpdate !== []) {
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

    private function handleConfigUpdates(AppEntity $app, Manifest $manifest, bool $install): bool
    {
        $config = $this->getAppConfig($app);
        if ($config === null) {
            return false;
        }

        $configError = $this->configValidator->validate($manifest, null)->first();
        if ($configError) {
            // only one error can be in the returned collection
            throw AppException::invalidConfiguration($manifest->getMetadata()->getName(), $configError);
        }

        $this->systemConfigService->saveConfig($config, $app->getName() . '.config.', $install);

        return true;
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
     * @param callable(PersisterInterface): void $callback
     */
    private function runPersisters(callable $callback): void
    {
        foreach ($this->persisters as $persister) {
            $callback($persister);
        }
    }

    private function ensureMeetsRequirements(Manifest $manifest): void
    {
        $violations = $this->requirementsValidator->validate($manifest);
        if (\count($violations) > 0) {
            throw AppException::requirementsNotMet(...$violations);
        }
    }
}

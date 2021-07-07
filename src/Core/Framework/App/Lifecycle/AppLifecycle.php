<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\InvalidAppConfigurationException;
use Shopware\Core\Framework\App\Lifecycle\Persister\ActionButtonPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\CmsBlockPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\TemplatePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Module;
use Shopware\Core\Framework\App\Validation\ConfigValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class AppLifecycle extends AbstractAppLifecycle
{
    private EntityRepositoryInterface $appRepository;

    private PermissionPersister $permissionPersister;

    private CustomFieldPersister $customFieldPersister;

    private AbstractAppLoader $appLoader;

    private EventDispatcherInterface $eventDispatcher;

    private AppRegistrationService $registrationService;

    private AppStateService $appStateService;

    private ActionButtonPersister $actionButtonPersister;

    private TemplatePersister $templatePersister;

    private WebhookPersister $webhookPersister;

    private PaymentMethodPersister $paymentMethodPersister;

    /**
     * @internal (flag:FEATURE_NEXT_14408) make persister not nullable on removal
     */
    private ?CmsBlockPersister $cmsBlockPersister;

    private EntityRepositoryInterface $languageRepository;

    private SystemConfigService $systemConfigService;

    private ConfigValidator $configValidator;

    private string $projectDir;

    private EntityRepositoryInterface $integrationRepository;

    private EntityRepositoryInterface $aclRoleRepository;

    public function __construct(
        EntityRepositoryInterface $appRepository,
        PermissionPersister $permissionPersister,
        CustomFieldPersister $customFieldPersister,
        ActionButtonPersister $actionButtonPersister,
        TemplatePersister $templatePersister,
        WebhookPersister $webhookPersister,
        PaymentMethodPersister $paymentMethodPersister,
        ?CmsBlockPersister $cmsBlockPersister,
        AbstractAppLoader $appLoader,
        EventDispatcherInterface $eventDispatcher,
        AppRegistrationService $registrationService,
        AppStateService $appStateService,
        EntityRepositoryInterface $languageRepository,
        SystemConfigService $systemConfigService,
        ConfigValidator $configValidator,
        EntityRepositoryInterface $integrationRepository,
        EntityRepositoryInterface $aclRoleRepository,
        string $projectDir
    ) {
        $this->appRepository = $appRepository;
        $this->permissionPersister = $permissionPersister;
        $this->customFieldPersister = $customFieldPersister;
        $this->webhookPersister = $webhookPersister;
        $this->paymentMethodPersister = $paymentMethodPersister;
        $this->cmsBlockPersister = $cmsBlockPersister;
        $this->appLoader = $appLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->registrationService = $registrationService;
        $this->projectDir = $projectDir;
        $this->appStateService = $appStateService;
        $this->actionButtonPersister = $actionButtonPersister;
        $this->templatePersister = $templatePersister;
        $this->languageRepository = $languageRepository;
        $this->systemConfigService = $systemConfigService;
        $this->configValidator = $configValidator;
        $this->integrationRepository = $integrationRepository;
        $this->aclRoleRepository = $aclRoleRepository;
    }

    public function getDecorated(): AbstractAppLifecycle
    {
        throw new DecorationPatternException(self::class);
    }

    public function install(Manifest $manifest, bool $activate, Context $context): void
    {
        $app = $this->loadAppByName($manifest->getMetadata()->getName(), $context);

        if ($app) {
            throw new AppAlreadyInstalledException($manifest->getMetadata()->getName());
        }

        $defaultLocale = $this->getDefaultLocale($context);
        $metadata = $manifest->getMetadata()->toArray($defaultLocale);
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $metadata = $this->enrichInstallMetadata($manifest, $metadata, $roleId);

        $app = $this->updateApp($manifest, $metadata, $appId, $roleId, $defaultLocale, $context, true);
        $this->eventDispatcher->dispatch(
            new AppInstalledEvent($app, $manifest, $context)
        );

        if ($activate) {
            $this->appStateService->activateApp($appId, $context);
        }

        $this->updateAclRole($app->getName(), $context);
    }

    public function update(Manifest $manifest, array $app, Context $context): void
    {
        $defaultLocale = $this->getDefaultLocale($context);
        $metadata = $manifest->getMetadata()->toArray($defaultLocale);
        $appEntity = $this->updateApp($manifest, $metadata, $app['id'], $app['roleId'], $defaultLocale, $context, false);

        $this->eventDispatcher->dispatch(
            new AppUpdatedEvent($appEntity, $manifest, $context)
        );
    }

    public function delete(string $appName, array $app, Context $context, bool $keepUserData = false): void
    {
        $appEntity = $this->loadApp($app['id'], $context);

        if ($appEntity->isActive()) {
            $this->appStateService->deactivateApp($appEntity->getId(), $context);
        }

        $this->removeAppAndRole($appEntity, $context, $keepUserData, true);
    }

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
        /** @var string $secretAccessKey */
        $secretAccessKey = $metadata['accessToken'] ?? '';
        unset($metadata['accessToken'], $metadata['icon']);
        $metadata['path'] = str_replace($this->projectDir . '/', '', $manifest->getPath());
        $metadata['id'] = $id;
        $metadata['modules'] = [];
        $metadata['iconRaw'] = $this->appLoader->getIcon($manifest);
        $metadata['cookies'] = $manifest->getCookies() !== null ? $manifest->getCookies()->getCookies() : [];

        $this->updateMetadata($metadata, $context);
        $this->permissionPersister->updatePrivileges($manifest->getPermissions(), $roleId);

        $app = $this->loadApp($id, $context);

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
        // we need a app secret to securely communicate with apps
        // therefore we only install action-buttons, webhooks and modules if we have a secret
        if ($app->getAppSecret()) {
            $this->actionButtonPersister->updateActions($manifest, $id, $defaultLocale, $context);
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($manifest, $id, $defaultLocale): void {
                $this->webhookPersister->updateWebhooks($manifest, $id, $defaultLocale, $context);
            });
            $this->paymentMethodPersister->updatePaymentMethods($manifest, $id, $defaultLocale, $context);
            $this->updateModules($manifest, $id, $defaultLocale, $context);
        }

        $this->templatePersister->updateTemplates($manifest, $id, $context);
        $this->customFieldPersister->updateCustomFields($manifest, $id, $context);

        if (Feature::isActive('FEATURE_NEXT_14408') && $this->cmsBlockPersister !== null) {
            $cmsExtensions = $this->appLoader->getCmsExtensions($app);
            if ($cmsExtensions) {
                $this->cmsBlockPersister->updateCmsBlocks($cmsExtensions, $id, $defaultLocale, $context);
            }
        }

        $config = $this->appLoader->getConfiguration($app);
        if ($config) {
            $errors = $this->configValidator->validate($manifest, null);
            $configError = $errors->first();

            if ($configError) {
                // only one error can be in the returned collection
                throw new InvalidAppConfigurationException($configError);
            }

            $this->systemConfigService->saveConfig(
                $config,
                $app->getName() . '.config.',
                $install
            );
            $this->appRepository->update([
                [
                    'id' => $app->getId(),
                    'configurable' => true,
                ],
            ], $context);
        }

        return $app;
    }

    private function removeAppAndRole(AppEntity $app, Context $context, bool $keepUserData = false, bool $softDelete = false): void
    {
        // throw event before deleting app from db as it may be delivered via webhook to the deleted app
        $this->eventDispatcher->dispatch(
            new AppDeletedEvent($app->getId(), $context, $keepUserData)
        );

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($app, $softDelete): void {
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

    private function updateMetadata(array $metadata, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($metadata): void {
            $this->appRepository->upsert([$metadata], $context);
        });
    }

    private function enrichInstallMetadata(Manifest $manifest, array $metadata, string $roleId): array
    {
        $secret = AccessKeyHelper::generateSecretAccessKey();

        $metadata['integration'] = [
            'label' => $manifest->getMetadata()->getName(),
            'writeAccess' => true,
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
        /** @var AppEntity $app */
        $app = $this->appRepository->search(new Criteria([$id]), $context)->first();

        return $app;
    }

    private function loadAppByName(string $name, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var AppEntity|null $app */
        $app = $this->appRepository->search($criteria, $context)->first();

        return $app;
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

        /** @var LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, $context)->first();
        /** @var LocaleEntity $locale */
        $locale = $language->getLocale();

        return $locale->getCode();
    }

    private function updateAclRole(string $appName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [new EqualsFilter('users.id', null)]
        ));
        $roles = $this->aclRoleRepository->search($criteria, $context);

        $newPrivileges = [
            'app.' . $appName,
        ];
        $dataUpdate = [];

        foreach ($roles as $role) {
            $currentPrivileges = $role->getPrivileges();

            if (\in_array('app.all', $currentPrivileges, true)) {
                $currentPrivileges = array_merge($currentPrivileges, $newPrivileges);
                $currentPrivileges = array_unique($currentPrivileges);

                array_push($dataUpdate, [
                    'id' => $role->getId(),
                    'privileges' => $currentPrivileges,
                ]);
            }
        }

        if (\count($dataUpdate) > 0) {
            $this->aclRoleRepository->update($dataUpdate, $context);
        }
    }

    private function deleteAclRole(string $appName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('app.id', null));
        $roles = $this->aclRoleRepository->search($criteria, $context);

        $appPrivileges = 'app.' . $appName;
        $dataUpdate = [];

        foreach ($roles as $role) {
            $currentPrivileges = $role->getPrivileges();

            if (($key = array_search($appPrivileges, $currentPrivileges, true)) !== false) {
                unset($currentPrivileges[$key]);

                array_push($dataUpdate, [
                    'id' => $role->getId(),
                    'privileges' => $currentPrivileges,
                ]);
            }
        }

        if (\count($dataUpdate) > 0) {
            $this->aclRoleRepository->update($dataUpdate, $context);
        }
    }
}

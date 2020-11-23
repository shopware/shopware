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
use Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\TemplatePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Cookies;
use Shopware\Core\Framework\App\Manifest\Xml\Module;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AppLifecycle extends AbstractAppLifecycle
{
    private const ALLOWED_APP_CONFIGURATION_COMPONENTS = [
        'sw-entity-single-select',
        'sw-entity-multi-id-select',
        'sw-media-field',
        'sw-text-editor',
        'sw-snippet-field',
    ];

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var PermissionPersister
     */
    private $permissionPersister;

    /**
     * @var CustomFieldPersister
     */
    private $customFieldPersister;

    /**
     * @var AbstractAppLoader
     */
    private $appLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AppRegistrationService
     */
    private $registrationService;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var AppStateService
     */
    private $appStateService;

    /**
     * @var ActionButtonPersister
     */
    private $actionButtonPersister;

    /**
     * @var TemplatePersister
     */
    private $templatePersister;

    /**
     * @var WebhookPersister
     */
    private $webhookPersister;

    /**
     * @var EntityRepositoryInterface
     */
    private $aclRoleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /*
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $appRepository,
        PermissionPersister $permissionPersister,
        CustomFieldPersister $customFieldPersister,
        ActionButtonPersister $actionButtonPersister,
        TemplatePersister $templatePersister,
        WebhookPersister $webhookPersister,
        AbstractAppLoader $appLoader,
        EventDispatcherInterface $eventDispatcher,
        AppRegistrationService $registrationService,
        AppStateService $appStateService,
        EntityRepositoryInterface $aclRoleRepository,
        EntityRepositoryInterface $languageRepository,
        SystemConfigService $systemConfigService,
        string $projectDir
    ) {
        $this->appRepository = $appRepository;
        $this->permissionPersister = $permissionPersister;
        $this->customFieldPersister = $customFieldPersister;
        $this->webhookPersister = $webhookPersister;
        $this->appLoader = $appLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->registrationService = $registrationService;
        $this->projectDir = $projectDir;
        $this->appStateService = $appStateService;
        $this->actionButtonPersister = $actionButtonPersister;
        $this->templatePersister = $templatePersister;
        $this->aclRoleRepository = $aclRoleRepository;
        $this->languageRepository = $languageRepository;
        $this->systemConfigService = $systemConfigService;
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
    }

    public function update(Manifest $manifest, array $appData, Context $context): void
    {
        $defaultLocale = $this->getDefaultLocale($context);
        $metadata = $manifest->getMetadata()->toArray($defaultLocale);
        $app = $this->updateApp($manifest, $metadata, $appData['id'], $appData['roleId'], $defaultLocale, $context, false);

        $this->eventDispatcher->dispatch(
            new AppUpdatedEvent($app, $manifest, $context)
        );
    }

    public function delete(string $appName, array $appData, Context $context): void
    {
        $app = $this->loadApp($appData['id'], $context);

        if ($app->isActive()) {
            $this->appStateService->deactivateApp($appData['id'], $context);
        }

        // throw event before deleting app from db as it may be delivered via webhook to the deleted app
        $this->eventDispatcher->dispatch(
            new AppDeletedEvent($appData['id'], $context)
        );

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($appData): void {
            $this->appRepository->delete([['id' => $appData['id']]], $context);
            $this->aclRoleRepository->delete([['id' => $appData['roleId']]], $context);
        });
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

        $this->updateMetadata($metadata, $context);
        $this->permissionPersister->updatePrivileges($manifest->getPermissions(), $roleId);

        if ($install && $manifest->getSetup()) {
            try {
                $this->registrationService->registerApp($manifest, $id, $secretAccessKey, $context);
            } catch (AppRegistrationException $e) {
                $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
                    $this->appRepository->delete([['id' => $id]], $context);
                });
                $this->permissionPersister->removeRole($roleId);

                throw $e;
            }
        }

        $app = $this->loadApp($id, $context);
        // we need a app secret to securely communicate with apps
        // therefore we only install action-buttons, webhooks and modules if we have a secret
        if ($app->getAppSecret()) {
            $this->actionButtonPersister->updateActions($manifest, $id, $defaultLocale, $context);
            $this->webhookPersister->updateWebhooks($manifest, $id, $defaultLocale, $context);
            $this->updateModules($manifest, $id, $defaultLocale, $context);
        }

        $this->templatePersister->updateTemplates($manifest, $id, $context);
        $this->customFieldPersister->updateCustomFields($manifest, $id, $context);

        $this->updateCookies($manifest, $id, $context);

        $config = $this->appLoader->getConfiguration($app);
        if ($config) {
            $this->verifyConfig($config);
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
        if (!$manifest->getAdmin()) {
            return;
        }

        $payload = [
            'id' => $id,
            'modules' => array_reduce(
                $manifest->getAdmin()->getModules(),
                static function (array $modules, Module $module) use ($defaultLocale) {
                    $modules[] = $module->toArray($defaultLocale);

                    return $modules;
                },
                []
            ),
        ];

        $this->appRepository->update([$payload], $context);
    }

    private function updateCookies(Manifest $manifest, string $id, Context $context): void
    {
        if (!($manifest->getCookies() instanceof Cookies)) {
            return;
        }

        $payload = [
            'id' => $id,
            'cookies' => $manifest->getCookies()->getCookies(),
        ];

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

    private function verifyConfig(array $config): void
    {
        foreach ($config as $card) {
            foreach ($card['elements'] as $element) {
                // Rendering of custom admin components via <component> element is not allowed for apps
                // as it may lead to code execution by apps in the administration
                if (array_key_exists('componentName', $element) && !in_array($element['componentName'], self::ALLOWED_APP_CONFIGURATION_COMPONENTS, true)) {
                    throw new InvalidAppConfigurationException($element['componentName']);
                }
            }
        }
    }
}

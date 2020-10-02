<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\Persister\ActionButtonPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\TemplatePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Module;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AppLifecycle extends AbstractAppLifecycle
{
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
    }

    public function getDecorated(): AbstractAppLifecycle
    {
        throw new DecorationPatternException(self::class);
    }

    public function install(Manifest $manifest, bool $activate, Context $context): void
    {
        $metadata = $manifest->getMetadata()->toArray();
        $appId = Uuid::randomHex();
        $roleId = Uuid::randomHex();
        $metadata = $this->enrichInstallMetadata($manifest, $metadata, $roleId);

        $app = $this->updateApp($manifest, $metadata, $appId, $roleId, $context, true);
        $this->eventDispatcher->dispatch(
            new AppInstalledEvent($app, $manifest, $context)
        );

        if ($activate) {
            $this->appStateService->activateApp($appId, $context);
        }
    }

    public function update(Manifest $manifest, array $appData, Context $context): void
    {
        $metadata = $manifest->getMetadata()->toArray();
        $app = $this->updateApp($manifest, $metadata, $appData['id'], $appData['roleId'], $context, false);

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

        $this->appRepository->delete([['id' => $appData['id']]], $context);
    }

    private function updateApp(
        Manifest $manifest,
        array $metadata,
        string $id,
        string $roleId,
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
            $this->actionButtonPersister->updateActions($manifest, $id, $context);
            $this->webhookPersister->updateWebhooks($manifest, $id, $context);
            $this->updateModules($manifest, $id, $context);
        }

        $this->templatePersister->updateTemplates($manifest, $id, $context);
        $this->customFieldPersister->updateCustomFields($manifest, $id, $context);

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

    private function updateModules(Manifest $manifest, string $id, Context $context): void
    {
        if (!$manifest->getAdmin()) {
            return;
        }

        $payload = [
            'id' => $id,
            'modules' => array_reduce(
                $manifest->getAdmin()->getModules(),
                static function (array $modules, Module $module) {
                    $modules[] = $module->toArray();

                    return $modules;
                },
                []
            ),
        ];

        $this->appRepository->update([$payload], $context);
    }
}

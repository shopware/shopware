<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\DTO\Service as ServiceDto;
use Shopware\Core\Service\Event\ServiceInstalledEvent;
use Shopware\Core\Service\Event\ServiceUpdatedEvent;
use Shopware\Core\Service\Requirement\Gate;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Installs, updates and uninstalls self-managed service apps.
 *
 * A service is a self-managed app whose app row may only be mutated internally; the
 * {@see \Shopware\Core\Service\Subscriber\ServiceWriteProtectionSubscriber} rejects any such write
 * outside the system scope. The state-changing operations therefore elevate to the system scope
 * themselves, so every caller (controllers, subscribers, handlers) is safe regardless of its context.
 *
 * @internal
 */
#[Package('framework')]
class ServiceLifecycle
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly AppManager $appManager,
        private readonly EntityRepository $appRepository,
        private readonly ServiceStorage $serviceStorage,
        private readonly LoggerInterface $logger,
        private readonly ManifestFactory $manifestFactory,
        private readonly ServiceSourceResolver $sourceResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequirementsValidator $requirementsValidator,
        private readonly Client $serviceRegistryClient,
        private readonly ServiceClientFactory $serviceClientFactory,
    ) {
    }

    /**
     * A service was discovered in the registry and is not yet installed: install it if its
     * installation-gating requirements are met. Returns true if it ended up installed.
     */
    public function install(ServiceEntry $entry, Context $context): bool
    {
        try {
            $appInfo = $this->serviceClientFactory->newFor($entry)->latestAppInfo();
        } catch (ServiceException $e) {
            // noop - errors will be recorded in the service

            return false;
        }

        if (!$this->requirementsValidator->isSatisfied($appInfo->requirements, Gate::INSTALLATION)) {
            return false;
        }

        return $this->performInstall($entry, $appInfo, $context);
    }

    /**
     * A new version of an installed service was published: update it in place if the new version's
     * installation-gating requirements are met, otherwise uninstall it (the new version may no longer
     * be allowed to exist here).
     */
    public function update(string $name, Context $context): void
    {
        try {
            $entry = $this->serviceRegistryClient->get($name);
            $appInfo = $this->serviceClientFactory->newFor($entry)->latestAppInfo();
        } catch (ServiceException $e) {
            // registry unavailable or no longer lists the service; nothing to do, the service retries later
            return;
        }

        $service = $this->serviceStorage->findByName($entry->name, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $entry->name);
        }

        if ($service->version === $appInfo->revision) {
            return;
        }

        if (!$this->requirementsValidator->isSatisfied($appInfo->requirements, Gate::INSTALLATION)) {
            $this->uninstall($entry->name, $context);

            return;
        }

        $this->performUpdate($entry, $appInfo, $service, $context);
    }

    /**
     * Re-evaluate every installed service and uninstall any whose installation-gating requirements are
     * no longer met (e.g. services were disabled as a unit).
     */
    public function reevaluateInstalled(Context $context): void
    {
        foreach ($this->serviceStorage->findAll($context) as $service) {
            if (!$this->requirementsValidator->isSatisfied($service->requirements, Gate::INSTALLATION)) {
                $this->uninstall($service->name, $context);
            }
        }
    }

    public function activate(string $serviceName, Context $context): void
    {
        $service = $this->serviceStorage->findByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        if (!$this->requirementsValidator->permitsStateChange($service->requirements)) {
            throw ServiceException::stateChangeNotPermitted($serviceName);
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($service): void {
            $this->appManager->activate($service->app, $context);
        });
    }

    public function deactivate(string $serviceName, Context $context): void
    {
        $service = $this->serviceStorage->findByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        if (!$this->requirementsValidator->permitsStateChange($service->requirements)) {
            throw ServiceException::stateChangeNotPermitted($serviceName);
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($service): void {
            $this->appManager->deactivate($service->app, $context);
        });
    }

    public function uninstall(string $serviceName, Context $context): void
    {
        $service = $this->serviceStorage->findByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($service): void {
            $this->appManager->uninstall($service->app, $context, true);
        });
    }

    /**
     * If a non-service app exists with the same name as the service, return its ID.
     */
    public function getAppIdForAppWithSameNameAsService(ServiceEntry $serviceEntry, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $serviceEntry->name));
        $criteria->addFilter(new EqualsFilter('selfManaged', false));
        $criteria->setLimit(1);

        return $this->appRepository->search($criteria, $context)->getEntities()->first()?->getId();
    }

    private function performInstall(ServiceEntry $entry, AppInfo $appInfo, Context $context): bool
    {
        $appId = $this->getAppIdForAppWithSameNameAsService($entry, $context);

        if ($appId) {
            return $this->upgradeAppToService($appId, $entry, $appInfo, $context);
        }

        try {
            $fs = $this->sourceResolver->filesystemForVersion($appInfo);
        } catch (AppException $e) {
            $this->logger->debug(\sprintf('Cannot install service "%s" because of error: "%s"', $entry->name, $e->getMessage()));

            return false;
        }

        try {
            $manifest = $this->createManifest($fs->path('manifest.xml'), $entry->host, $appInfo);
        } catch (AppXmlParsingException $e) {
            $this->logger->warning(\sprintf('Cannot install service "%s" because of invalid manifest: "%s"', $entry->name, $e->getMessage()));

            return false;
        }

        try {
            $this->appManager->install(
                $manifest,
                new AppInstallParameters(activate: $entry->activateOnInstall, strictValidation: true),
                Context::createDefaultContext()
            );

            $this->logger->debug(\sprintf('Installed service "%s"', $entry->name));

            $this->eventDispatcher->dispatch(new ServiceInstalledEvent($entry->name, $context));

            return true;
        } catch (\Exception $e) {
            $this->logger->warning(\sprintf('Cannot install service "%s" because of error: "%s"', $entry->name, $e->getMessage()));

            return false;
        }
    }

    private function performUpdate(ServiceEntry $entry, AppInfo $appInfo, ServiceDto $service, Context $context): bool
    {
        if ($service->version === $appInfo->revision) {
            return true;
        }

        try {
            $fs = $this->sourceResolver->filesystemForVersion($appInfo);
        } catch (AppException $e) {
            $this->logger->debug(\sprintf('Cannot update service "%s" because of error: "%s"', $entry->name, $e->getMessage()));

            return false;
        }

        try {
            $manifest = $this->createManifest($fs->path('manifest.xml'), $entry->host, $appInfo);
        } catch (AppXmlParsingException $e) {
            $this->logger->warning(\sprintf('Cannot update service "%s" because of invalid manifest: "%s"', $entry->name, $e->getMessage()));

            return false;
        }

        try {
            $this->appManager->update(
                $manifest,
                new AppUpdateParameters(strictValidation: true),
                $service->app,
                $context
            );
            $this->logger->debug(\sprintf('Updated service "%s"', $entry->name));

            $this->eventDispatcher->dispatch(new ServiceUpdatedEvent($entry->name, $context));

            return true;
        } catch (\Exception $e) {
            $this->logger->debug(\sprintf('Cannot update service "%s" because of error: "%s"', $entry->name, $e->getMessage()));

            return false;
        }
    }

    private function createManifest(string $manifestPath, string $host, AppInfo $appInfo): Manifest
    {
        $manifest = $this->manifestFactory->createFromXmlFile($manifestPath);
        $manifest->setPath($host);
        $manifest->setSourceConfig($appInfo->toArray());
        $manifest->getMetadata()->setVersion($appInfo->revision);
        $manifest->getMetadata()->setSelfManaged(true);

        return $manifest;
    }

    private function upgradeAppToService(string $appId, ServiceEntry $entry, AppInfo $appInfo, Context $context): bool
    {
        $this->appRepository->update(
            [
                [
                    'id' => $appId,
                    'selfManaged' => true,
                ],
            ],
            $context
        );

        // it was possibly disabled during the update process; this is a requirement-driven
        // activation, so it must not be subject to the manual state change policy
        $service = $this->serviceStorage->findByName($entry->name, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $entry->name);
        }

        $this->appManager->activate($service->app, $context);

        $service = $this->serviceStorage->findByName($entry->name, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $entry->name);
        }

        $result = $this->performUpdate($entry, $appInfo, $service, $context);

        if ($result) {
            return true;
        }

        // reset it back to a normal app
        $this->appRepository->update(
            [
                [
                    'id' => $appId,
                    'selfManaged' => false,
                ],
            ],
            $context
        );

        return false;
    }
}

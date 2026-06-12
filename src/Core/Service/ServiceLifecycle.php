<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
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
use Shopware\Core\Service\Event\ServiceInstalledEvent;
use Shopware\Core\Service\Event\ServiceUpdatedEvent;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class ServiceLifecycle
{
    /**
     * @internal
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly Client $serviceRegistryClient,
        private readonly ServiceClientFactory $serviceClientFactory,
        private readonly AppManager $appManager,
        private readonly EntityRepository $appRepository,
        private readonly ServiceStorage $serviceStorage,
        private readonly LoggerInterface $logger,
        private readonly ManifestFactory $manifestFactory,
        private readonly ServiceSourceResolver $sourceResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequirementsValidator $requirementsValidator,
    ) {
    }

    public function install(ServiceEntry $serviceEntry, Context $context): bool
    {
        $appId = $this->getAppIdForAppWithSameNameAsService($serviceEntry, $context);

        if ($appId) {
            return $this->upgradeAppToService($appId, $serviceEntry, $context);
        }

        try {
            $appInfo = $this->serviceClientFactory->newFor($serviceEntry)->latestAppInfo();
        } catch (ServiceException $e) {
            // noop - errors will be recorded in the service

            return false;
        }

        // do not install invalid releases
        if (!$this->requirementsValidator->isValidSet($appInfo->requirements)) {
            $this->logger->debug(\sprintf('Cannot install service "%s" because of invalid requirements: "%s"', $serviceEntry->name, implode(', ', $appInfo->requirements)));

            return false;
        }

        try {
            $fs = $this->sourceResolver->filesystemForVersion($appInfo);
        } catch (AppException $e) {
            $this->logger->debug(\sprintf('Cannot install service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }

        $manifest = $this->createManifest($fs->path('manifest.xml'), $serviceEntry->host, $appInfo);

        try {
            $this->appManager->install(
                $manifest,
                new AppInstallParameters(activate: $serviceEntry->activateOnInstall),
                Context::createDefaultContext()
            );

            $this->logger->debug(\sprintf('Installed service "%s"', $serviceEntry->name));

            $this->eventDispatcher->dispatch(new ServiceInstalledEvent($serviceEntry->name, $context));

            return true;
        } catch (\Exception $e) {
            $this->logger->warning(\sprintf('Cannot install service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }
    }

    public function update(string $serviceName, Context $context): bool
    {
        $serviceEntry = $this->serviceRegistryClient->get($serviceName);

        $service = $this->serviceStorage->findByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        try {
            $latestAppInfo = $this->serviceClientFactory->newFor($serviceEntry)->latestAppInfo();
        } catch (ServiceException $e) {
            $this->logger->debug(\sprintf('Cannot update service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }

        // if it's the same version, bail
        if ($service->version === $latestAppInfo->revision) {
            return true;
        }

        // do not update invalid releases
        if (!$this->requirementsValidator->isValidSet($latestAppInfo->requirements)) {
            $this->logger->debug(\sprintf('Cannot update service "%s" because of invalid requirements: "%s"', $serviceEntry->name, implode(', ', $latestAppInfo->requirements)));

            return false;
        }

        try {
            $fs = $this->sourceResolver->filesystemForVersion($latestAppInfo);
        } catch (AppException $e) {
            $this->logger->debug(\sprintf('Cannot update service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }

        $manifest = $this->createManifest($fs->path('manifest.xml'), $serviceEntry->host, $latestAppInfo);

        try {
            $this->appManager->update(
                $manifest,
                new AppUpdateParameters(),
                $service->app,
                $context
            );
            $this->logger->debug(\sprintf('Installed service "%s"', $serviceEntry->name));

            $this->eventDispatcher->dispatch(new ServiceUpdatedEvent($serviceName, $context));

            return true;
        } catch (\Exception $e) {
            $this->logger->debug(\sprintf('Cannot update service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }
    }

    public function activate(string $serviceName, Context $context): void
    {
        $service = $this->serviceStorage->findByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        $this->appManager->activate($service->app, $context);
    }

    public function deactivate(string $serviceName, Context $context): void
    {
        $service = $this->serviceStorage->findByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        $this->appManager->deactivate($service->app, $context);
    }

    public function uninstall(string $serviceName, Context $context): void
    {
        $service = $this->serviceStorage->findByName($serviceName, $context);

        if (!$service) {
            throw ServiceException::notFound('name', $serviceName);
        }

        $this->appManager->uninstall($service->app, $context);
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

    private function createManifest(string $manifestPath, string $host, AppInfo $appInfo): Manifest
    {
        $manifest = $this->manifestFactory->createFromXmlFile($manifestPath);
        $manifest->setPath($host);
        $manifest->setSourceConfig($appInfo->toArray());
        $manifest->getMetadata()->setVersion($appInfo->revision);
        $manifest->getMetadata()->setSelfManaged(true);

        return $manifest;
    }

    private function upgradeAppToService(string $appId, ServiceEntry $entry, Context $context): bool
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

        // it was possibly disabled during the update process
        $this->activate($entry->name, $context);

        $result = $this->update($entry->name, $context);

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

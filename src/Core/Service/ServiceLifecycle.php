<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ServiceLifecycle
{
    /**
     * @internal
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly ServiceRegistryClient $serviceRegistryClient,
        private readonly ServiceClientFactory $serviceClientFactory,
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly EntityRepository $appRepository,
        private readonly LoggerInterface $logger,
        private readonly ManifestFactory $manifestFactory,
        private readonly ServiceSourceResolver $sourceResolver,
        private readonly AppStateService $appStateService
    ) {
    }

    public function install(ServiceRegistryEntry $serviceEntry, Context $context): bool
    {
        $appId = $this->getAppIdForAppWithSameNameAsService($serviceEntry, $context);

        if ($appId) {
            return $this->upgradeAppToService($appId, $serviceEntry, $context);
        }

        try {
            $appInfo = $this->serviceClientFactory->newFor($serviceEntry)->latestAppInfo();
        } catch (ServiceException $e) {
            $this->logger->error(\sprintf('Cannot install service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }

        try {
            $fs = $this->sourceResolver->filesystemForVersion($appInfo);
        } catch (AppException $e) {
            $this->logger->error(\sprintf('Cannot install service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }

        $manifest = $this->createManifest($fs->path('manifest.xml'), $serviceEntry->host, $appInfo);

        try {
            $this->appLifecycle->install($manifest, $serviceEntry->activateOnInstall, Context::createDefaultContext());
            $this->logger->debug(\sprintf('Installed service "%s"', $serviceEntry->name));

            return true;
        } catch (\Exception $e) {
            $this->logger->error(\sprintf('Cannot install service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }
    }

    public function update(string $serviceName, Context $context): bool
    {
        $serviceEntry = $this->serviceRegistryClient->get($serviceName);

        $app = $this->loadServiceByName($serviceName, $context);

        if (!$app) {
            throw ServiceException::notFound('name', $serviceName);
        }

        try {
            $latestAppInfo = $this->serviceClientFactory->newFor($serviceEntry)->latestAppInfo();
        } catch (ServiceException $e) {
            $this->logger->error(\sprintf('Cannot update service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }

        // if it's the same version, bail
        if ($app->getVersion() === $latestAppInfo->revision) {
            return true;
        }

        try {
            $fs = $this->sourceResolver->filesystemForVersion($latestAppInfo);
        } catch (AppException $e) {
            $this->logger->error(\sprintf('Cannot update service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }

        $manifest = $this->createManifest($fs->path('manifest.xml'), $serviceEntry->host, $latestAppInfo);

        try {
            $this->appLifecycle->update(
                $manifest,
                [
                    'id' => $app->getId(),
                    'roleId' => $app->getAclRoleId(),
                ],
                $context
            );
            $this->logger->debug(\sprintf('Installed service "%s"', $serviceEntry->name));

            return true;
        } catch (\Exception $e) {
            $this->logger->error(\sprintf('Cannot update service "%s" because of error: "%s"', $serviceEntry->name, $e->getMessage()));

            return false;
        }
    }

    /**
     * If a non-service app exists with the same name as the service, return its ID.
     */
    public function getAppIdForAppWithSameNameAsService(ServiceRegistryEntry $serviceEntry, Context $context): ?string
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

    private function loadServiceByName(string $name, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $criteria->addFilter(new EqualsFilter('selfManaged', true));

        return $this->appRepository->search($criteria, $context)->getEntities()->first();
    }

    private function upgradeAppToService(string $appId, ServiceRegistryEntry $entry, Context $context): bool
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
        $this->appStateService->activateApp($appId, $context);

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

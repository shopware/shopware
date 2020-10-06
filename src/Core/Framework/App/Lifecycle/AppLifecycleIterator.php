<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\CustomFieldTypeNotFoundException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class AppLifecycleIterator
{
    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var AbstractAppLoader
     */
    private $appLoader;

    public function __construct(
        EntityRepositoryInterface $appRepository,
        AbstractAppLoader $appLoader
    ) {
        $this->appRepository = $appRepository;
        $this->appLoader = $appLoader;
    }

    /**
     * @return Manifest[]
     */
    public function iterate(AbstractAppLifecycle $appLifecycle, bool $activate, Context $context): array
    {
        $appsFromFileSystem = $this->appLoader->load();
        $installedApps = $this->getRegisteredApps($context);

        $successfulUpdates = [];
        $fails = [];
        foreach ($appsFromFileSystem as $manifest) {
            try {
                if (!\array_key_exists($manifest->getMetadata()->getName(), $installedApps)) {
                    $appLifecycle->install($manifest, $activate, $context);
                    $successfulUpdates[] = $manifest->getMetadata()->getName();

                    continue;
                }

                $app = $installedApps[$manifest->getMetadata()->getName()];
                if (\version_compare($manifest->getMetadata()->getVersion(), $app['version']) > 0) {
                    $appLifecycle->update($manifest, $app, $context);
                }
                $successfulUpdates[] = $manifest->getMetadata()->getName();
            } catch (AppRegistrationException | CustomFieldTypeNotFoundException $exception) {
                $fails[] = $manifest;

                continue;
            }
        }

        $this->deleteNotFoundAndFailedInstallApps($successfulUpdates, $appLifecycle, $context);

        return $fails;
    }

    private function getRegisteredApps(Context $context): array
    {
        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $context)->getEntities();

        $appData = [];
        foreach ($apps as $app) {
            $appData[$app->getName()] = [
                'id' => $app->getId(),
                'version' => $app->getVersion(),
                'roleId' => $app->getAclRoleId(),
            ];
        }

        return $appData;
    }

    private function deleteNotFoundAndFailedInstallApps(
        array $successfulUpdates,
        AbstractAppLifecycle $appLifecycle,
        Context $context
    ): void {
        // refetch registered apps so we can remove apps where the installation failed
        $appsFromDb = $this->getRegisteredApps($context);
        foreach ($successfulUpdates as $app) {
            unset($appsFromDb[$app]);
        }
        foreach ($appsFromDb as $appName => $app) {
            $appLifecycle->delete($appName, $app, $context);
        }
    }
}

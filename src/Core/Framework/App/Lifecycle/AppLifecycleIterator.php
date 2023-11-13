<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class AppLifecycleIterator
{
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AbstractAppLoader $appLoader
    ) {
    }

    /**
     * @param array<string> $installAppNames Apps that should be installed
     *
     * @return list<array{manifest: Manifest, exception: \Exception}>
     */
    public function iterateOverApps(AbstractAppLifecycle $appLifecycle, bool $activate, Context $context, array $installAppNames = []): array
    {
        $appsFromFileSystem = $this->appLoader->load();
        $installedApps = $this->getRegisteredApps($context);

        $successfulUpdates = [];
        $fails = [];
        foreach ($appsFromFileSystem as $manifest) {
            if (\count($installAppNames) && !\in_array($manifest->getMetadata()->getName(), $installAppNames, true)) {
                continue;
            }

            try {
                if (!\array_key_exists($manifest->getMetadata()->getName(), $installedApps)) {
                    $appLifecycle->install($manifest, $activate, $context);
                    $successfulUpdates[] = $manifest->getMetadata()->getName();

                    continue;
                }

                $app = $installedApps[$manifest->getMetadata()->getName()];
                if (version_compare($manifest->getMetadata()->getVersion(), $app['version']) > 0) {
                    $appLifecycle->update($manifest, $app, $context);
                }
                $successfulUpdates[] = $manifest->getMetadata()->getName();
            } catch (\Exception $exception) {
                $fails[] = [
                    'manifest' => $manifest,
                    'exception' => $exception,
                ];
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

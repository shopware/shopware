<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Update;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Store\Exception\ExtensionRequiresNewPrivilegesException;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;

/**
 * @internal
 */
class AppUpdater extends AbstractAppUpdater
{
    private AbstractExtensionDataProvider $extensionDataProvider;

    private EntityRepositoryInterface $appRepo;

    private ExtensionDownloader $downloader;

    private AbstractStoreAppLifecycleService $appLifecycle;

    public function __construct(
        AbstractExtensionDataProvider $extensionDataProvider,
        EntityRepositoryInterface $appRepo,
        ExtensionDownloader $downloader,
        AbstractStoreAppLifecycleService $appLifecycle
    ) {
        $this->extensionDataProvider = $extensionDataProvider;
        $this->appRepo = $appRepo;
        $this->downloader = $downloader;
        $this->appLifecycle = $appLifecycle;
    }

    public function updateApps(Context $context): void
    {
        $extensions = $this->extensionDataProvider->getInstalledExtensions($context, true);
        $extensions = $extensions->filterByType(ExtensionStruct::EXTENSION_TYPE_APP);

        $outdatedApps = [];

        foreach ($extensions->getIterator() as $extension) {
            $id = $extension->getLocalId();
            if (!$id) {
                continue;
            }
            /** @var AppEntity $localApp */
            $localApp = $this->appRepo->search(new Criteria([$id]), $context)->first();
            $nextVersion = $extension->getLatestVersion();
            if (!$nextVersion) {
                continue;
            }

            if (version_compare($nextVersion, $localApp->getVersion()) > 0) {
                $outdatedApps[] = $extension;
            }
        }
        foreach ($outdatedApps as $app) {
            $this->downloader->download($app->getName(), $context);

            try {
                $this->appLifecycle->updateExtension($app->getName(), false, $context);
            } catch (ExtensionRequiresNewPrivilegesException $exception) {
                //nth
            }
        }
    }

    protected function getDecorated(): AbstractAppUpdater
    {
        throw new DecorationPatternException(self::class);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('checkout')]
class ExtensionDownloader
{
    private readonly string $relativePluginDir;

    public function __construct(
        private readonly EntityRepository $pluginRepository,
        private readonly StoreClient $storeClient,
        private readonly PluginManagementService $pluginManagementService,
        string $pluginDir,
        string $projectDir
    ) {
        $this->relativePluginDir = (new Filesystem())->makePathRelative($pluginDir, $projectDir);
    }

    public function download(string $technicalName, Context $context): PluginDownloadDataStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $technicalName));

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginRepository->search($criteria, $context)->first();

        if ($plugin !== null && $plugin->getManagedByComposer() && !str_starts_with($plugin->getPath() ?? '', $this->relativePluginDir)) {
            throw StoreException::cannotDeleteManaged($plugin->getName());
        }

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($technicalName, $context);
        } catch (ClientException $e) {
            throw new StoreApiException($e);
        }

        $this->pluginManagementService->downloadStorePlugin($data, $context);

        return $data;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginFileHashService;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;

/**
 * @internal
 */
#[Package('core')]
readonly class ExtensionChecksumLoader
{
    public function __construct(
        private EntityRepository $pluginRepository,
        private PluginFileHashService $pluginFileHashService,
    ) {
    }

    public function load(ExtensionCollection $localCollection, Context $context): ExtensionCollection
    {
        $this->addChecksumInformation($localCollection, $context);

        return $localCollection;
    }

    private function addChecksumInformation(ExtensionCollection $localCollection, Context $context): void
    {
        foreach ($localCollection as $extension) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', $extension->getName()));
            $plugin = $this->pluginRepository->search($criteria, $context)->first();
            if (!$plugin instanceof PluginEntity) {
                continue;
            }

            $pluginChecksumCheckResult = $this->pluginFileHashService->checkPluginForChanges($plugin);

            $extension->setChecksumFileMissing($pluginChecksumCheckResult->isFileMissing());
            $extension->setChecksumFileWrongVersion($pluginChecksumCheckResult->isWrongVersion());
            $extension->setNewFiles($pluginChecksumCheckResult->getNewFiles());
            $extension->setChangedFiles($pluginChecksumCheckResult->getChangedFiles());
            $extension->setMissingFiles($pluginChecksumCheckResult->getMissingFiles());
        }
    }
}

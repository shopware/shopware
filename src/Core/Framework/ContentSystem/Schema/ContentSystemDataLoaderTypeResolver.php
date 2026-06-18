<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves the source-to-capability map from the registered data loaders.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSystemDataLoaderTypeResolver extends AbstractContentSystemDataLoaderTypeResolver
{
    private ?ContentSystemDataLoaderTypeMap $map = null;

    public function __construct(
        private readonly DataLoaderProvider $dataLoaderProvider,
    ) {
    }

    public function resolve(): ContentSystemDataLoaderTypeMap
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $sourceToCapabilities = [];
        foreach ($this->dataLoaderProvider->getSources() as $source) {
            $sourceToCapabilities[$source] = $this->dataLoaderProvider->get($source)->producibleTypes();
        }

        return $this->map = new ContentSystemDataLoaderTypeMap($sourceToCapabilities);
    }
}

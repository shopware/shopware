<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Package('core')]
class FlysystemLastModifiedVersionStrategy implements VersionStrategyInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $cacheTag,
        private readonly FilesystemOperator $filesystem,
        private readonly TagAwareAdapterInterface $cacheAdapter
    ) {
    }

    public function getVersion(string $path): string
    {
        return $this->applyVersion($path);
    }

    public function applyVersion(string $path): string
    {
        $lastModified = $this->getLastModified($path);

        return $path . $lastModified;
    }

    private function getLastModified(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $cacheKey = 'metaDataFlysystem-' . md5($path);

        /** @var ItemInterface $item */
        $item = $this->cacheAdapter->getItem($cacheKey);

        if ($item->isHit()) {
            return (string) $item->get();
        }

        $metaData = '';
        if ($this->filesystem->fileExists($path)) {
            $metaData = '?' . $this->filesystem->lastModified($path);
        }

        $item->set($metaData);
        $item->tag($this->cacheTag);
        $this->cacheAdapter->saveDeferred($item);

        return (string) $item->get();
    }
}

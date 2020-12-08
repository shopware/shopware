<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FlysystemLastModifiedVersionStrategy implements VersionStrategyInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cacheAdapter;

    /**
     * @var string
     */
    private $cacheTag;

    public function __construct(string $cacheTag, FilesystemInterface $filesystem, TagAwareAdapterInterface $cacheAdapter)
    {
        $this->filesystem = $filesystem;
        $this->cacheAdapter = $cacheAdapter;
        $this->cacheTag = $cacheTag;
    }

    public function getVersion($path)
    {
        return $this->applyVersion($path);
    }

    public function applyVersion($path)
    {
        try {
            $metaData = $this->getMetaData($path);
        } catch (FileNotFoundException $e) {
            return $path;
        }

        return $path . '?' . $metaData['timestamp'] . ($metaData['size'] ?? '0');
    }

    private function getMetaData(string $path): array
    {
        $cacheKey = 'metaDataFlySystem-' . md5($path);

        /** @var ItemInterface $item */
        $item = $this->cacheAdapter->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $metaData = $this->filesystem->getMetadata($path);

        $item->set($metaData);
        $item->tag($this->cacheTag);
        $this->cacheAdapter->saveDeferred($item);

        return $item->get();
    }
}

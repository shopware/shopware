<?php declare(strict_types=1);

namespace Shopware\Administration\Framework\Twig;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Pentatrion\ViteBundle\Service\FileAccessor;
use Shopware\Core\Framework\Bundle as ShopwareBundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Asset\PackageInterface as AssetPackage;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[Package('framework')]
class ViteFileAccessorDecorator extends FileAccessor
{
    private readonly string $assetPath;

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $content = [];

    /**
     * @var array<string, array<string, string|null>>
     */
    private array $manifests = [];

    /**
     * @internal
     */
    public function __construct(
        array $configs,
        private readonly AssetPackage $package,
        private readonly KernelInterface $kernel,
        private readonly Filesystem $filesystem,
        private readonly FilesystemOperator $assetFilesystem,
        private readonly TagAwareAdapterInterface $cache,
    ) {
        $this->assetPath = $this->package->getUrl('');

        parent::__construct($this->assetPath, $configs);
    }

    public function hasFile(string $configName, string $fileType): bool
    {
        try {
            $bundle = $this->getBundleForConfig($configName);
        } catch (\InvalidArgumentException) {
            // we can't find a bundle with that name
            return false;
        }

        return $this->readManifest($bundle, $fileType) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(string $configName, string $fileType): array
    {
        $bundle = $this->getBundleForConfig($configName);
        // plain symfony bundles don't bring JS assets
        if (!$bundle instanceof ShopwareBundle) {
            return [];
        }

        return $this->getContent($fileType, $bundle);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBundleData(ShopwareBundle $bundle): array
    {
        return $this->getContent(self::ENTRYPOINTS, $bundle);
    }

    private function getRelativeFileLocation(string $fileType): string
    {
        return '/Resources/public/administration/.vite/' . self::FILES[$fileType];
    }

    /**
     * Reads the manifest from the bundle directory and falls back to the asset filesystem,
     * which holds the files when assets are published to remote storage (e.g. S3).
     */
    private function readManifest(BundleInterface $bundle, string $fileType): ?string
    {
        $name = $bundle->getName();
        if (\array_key_exists($fileType, $this->manifests[$name] ?? [])) {
            return $this->manifests[$name][$fileType];
        }

        return $this->manifests[$name][$fileType] = $this->loadManifest($bundle, $fileType);
    }

    private function loadManifest(BundleInterface $bundle, string $fileType): ?string
    {
        $localPath = $bundle->getPath() . $this->getRelativeFileLocation($fileType);
        if ($this->filesystem->exists($localPath)) {
            return $this->filesystem->readFile($localPath);
        }

        // same target directory as AssetService::getTargetDirectory()
        $remotePath = \sprintf(
            'bundles/%s/administration/.vite/%s',
            preg_replace('/bundle$/D', '', mb_strtolower($bundle->getName())),
            self::FILES[$fileType]
        );

        $cacheItem = $this->cache->getItem('viteManifest-' . Hasher::hash($this->assetPath . $remotePath));
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        try {
            $content = $this->assetFilesystem->fileExists($remotePath)
                ? $this->assetFilesystem->read($remotePath)
                : null;
        } catch (FilesystemException) {
            // do not cache failures, a short outage must not stick
            return null;
        }

        $cacheItem->set($content);
        $cacheItem->tag('asset-metaData');
        $this->cache->saveDeferred($cacheItem);

        return $content;
    }

    private function getBundleForConfig(string $configName): BundleInterface
    {
        if ($configName === '_default') {
            $configName = 'Administration';
        }

        return $this->kernel->getBundle($configName);
    }

    private function getTechnicalBundleName(ShopwareBundle $bundle): string
    {
        return str_replace('_', '-', $bundle->getContainerPrefix());
    }

    /**
     * @return array<string, mixed>
     */
    private function getContent(string $fileType, ShopwareBundle $bundle): array
    {
        // Depending on how many script tags are rendered, this method is called multiple times
        // Cache the content to avoid reading the file multiple times
        if (!isset($this->content[$bundle->getName()][$fileType])) {
            $raw = $this->readManifest($bundle, $fileType);

            if ($raw === null) {
                return $this->content[$bundle->getName()][$fileType] = [];
            }

            $content = json_decode(
                $raw,
                true,
                flags: \JSON_THROW_ON_ERROR
            );

            // Replace the base generated by Vite with the symfony asset path
            $content['base'] = $this->assetPath;

            $technicalBundleName = $this->getTechnicalBundleName($bundle);

            // Get all entry points for the administration
            foreach ($content['entryPoints'][$technicalBundleName] ?? [] as $key => $entrypoint) {
                // The entry points also contain configuration, for example "legacy" and a boolean value
                if (!\is_array($entrypoint)) {
                    continue;
                }

                // Prepend the asset path to every entry point
                foreach ($entrypoint as $index => $entry) {
                    $content['entryPoints'][$technicalBundleName][$key][$index] = \sprintf('%s%s', $this->assetPath, $entry);
                }
            }

            $this->content[$bundle->getName()][$fileType] = $content;
        }

        return $this->content[$bundle->getName()][$fileType];
    }
}

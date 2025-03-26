<?php

declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Shopware\Administration\Administration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Storefront;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
#[Package('discovery')]
class SnippetFilesFinder implements SnippetFilesFinderInterface
{
    /**
     * @param BundleInterface[] $plugins
     * @param BundleInterface[] $activePlugins
     * @param BundleInterface[] $bundles
     */
    public function __construct(
        public readonly array $plugins,
        public readonly array $activePlugins,
        public readonly array $bundles
    ) {
    }

    /**
     * @return string[]
     */
    public function findSnippetFiles(string $locale): array
    {
        $finder = (new Finder())
            ->files()
            ->exclude('node_modules')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->ignoreUnreadableDirs()
            ->name(\sprintf('%s.json', $locale))
            ->in($this->getBundlePaths());

        $iterator = $finder->getIterator();
        $files = [];

        foreach ($iterator as $file) {
            $files[] = $file->getRealPath();
        }

        return \array_unique($files);
    }

    /**
     * @return string[]
     */
    private function getBundlePaths(): array
    {
        return array_reduce(
            $this->bundles,
            fn ($carry, BundleInterface $bundle) => match (true) {
                $bundle instanceof Administration => [
                    ...$carry,
                    $bundle->getPath() . '/Resources/app/administration/src/app/snippet',
                    $bundle->getPath() . '/Resources/app/administration/src/module/*/snippet',
                    $bundle->getPath() . '/Resources/app/administration/src/app/component/*/*/snippet',
                ],
                $bundle instanceof Storefront => [
                    ...$carry,
                    $bundle->getPath() . '/Resources/app/administration/src/app/snippet',
                    $bundle->getPath() . '/Resources/app/administration/src/modules/*/snippet',
                ],
                default => (\in_array($bundle, $this->activePlugins, true) || !\in_array($bundle, $this->plugins, true))
                && file_exists($path = $bundle->getPath() . '/Resources/app/administration/src')
                    ? [...$carry, $path]
                    : $carry,
            },
            []
        );
    }
}

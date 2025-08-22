<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Kernel;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @description Loads storefront snippet files from the core, plugins, and apps into a SnippetFileCollection.
 */
#[Package('discovery')]
class SnippetFileLoader implements SnippetFileLoaderInterface
{
    private const ADMINISTRATION_BUNDLE_NAME = 'Administration';

    private const SCOPE_PLATFORM = 'Platform';

    private const SCOPE_PLUGINS = 'Plugins';

    /**
     * @internal
     */
    public function __construct(
        private readonly Kernel $kernel,
        private readonly Connection $connection,
        private readonly AppSnippetFileLoader $appSnippetFileLoader,
        private readonly ActiveAppsLoader $activeAppsLoader,
        private readonly TranslationConfig $config,
        private readonly TranslationLoader $translationLoader,
        private readonly Filesystem $translationReader,
    ) {
    }

    public function loadSnippetFilesIntoCollection(SnippetFileCollection $snippetFileCollection): void
    {
        $this->loadCoreSnippets($snippetFileCollection);
        // Legacy snippets must be loaded here to ensure their availability, as the locale cannot be checked at this point, and they might otherwise be missing.
        $this->loadLegacySnippets($snippetFileCollection);
        $this->loadAppSnippets($snippetFileCollection);
    }

    private function loadCoreSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        $exclude = $this->getInactivePluginNames();

        $localesBasePath = \mb_ltrim($this->translationLoader->getLocalesBasePath(), '/\\');

        // regular expression template that can be used for filtering or matching path parts
        $translationPathRegexpTemplate = '#^/?'
            . Path::join($localesBasePath, '(?P<locale>[a-zA-Z-0-9-_]+)', '(?P<component>%s)', '(?P<plugin>%s)')
            . '.*$#';
        $excludedPathsRegexp = array_map(
            fn (string $path) => \sprintf($translationPathRegexpTemplate, 'Plugins', $path),
            $exclude
        );

        $translationFiles = $this->translationReader
            ->listContents($localesBasePath, true)
            ->filter(fn (StorageAttributes $node) => $node->isFile())
            ->filter(fn (StorageAttributes $node) => \str_ends_with($node->path(), '.json'))
            ->filter(fn (StorageAttributes $node) => \preg_filter($excludedPathsRegexp, 'EXCLUDED', $node->path()) !== 'EXCLUDED');

        $isPluginPathCheckRegexp = \sprintf($translationPathRegexpTemplate, self::SCOPE_PLATFORM . '|' . self::SCOPE_PLUGINS, '');
        foreach ($translationFiles as $translationFile) {
            \preg_match($isPluginPathCheckRegexp, $translationFile->path(), $pathComponents);

            // Check if the path matches the expected structure. If not, the directory was modified and the file should be skipped.
            $validityCheck = \array_intersect_key($pathComponents, array_fill_keys(['locale', 'component'], true));
            if (\count($validityCheck) !== 2 || empty($pathComponents['locale']) || empty($pathComponents['component'])) {
                continue;
            }

            $technicalName = self::SCOPE_PLATFORM;
            if ($pathComponents['component'] === 'Plugins') {
                $technicalName = self::SCOPE_PLUGINS;
            }

            $fileInfo = new \SplFileInfo($translationFile->path());
            $fileName = $fileInfo->getBasename('.' . $fileInfo->getExtension());
            $isBase = str_contains($fileName, 'messages');

            if ($isBase) {
                $fileName = 'messages.' . $pathComponents['locale'];
            }

            $snippetFile = new RemoteSnippetFile(
                $fileName,
                $fileInfo->getPathname(),
                $pathComponents['locale'],
                'Shopware',
                $isBase,
                $technicalName,
            );

            $snippetFileCollection->add($snippetFile);
        }
    }

    /**
     * @return array<int<0, max>, string>
     */
    private function getInactivePluginNames(): array
    {
        $plugins = $this->kernel->getPluginLoader()->getPluginInstances()->getActives();

        $activeNames = [];
        foreach ($plugins as $plugin) {
            $activeNames[] = $this->config->getMappedPluginName($plugin);
        }

        return array_diff($this->config->plugins, $activeNames);
    }

    private function loadLegacySnippets(SnippetFileCollection $snippetFileCollection): void
    {
        try {
            /** @var array<string, string> $authors */
            $authors = $this->connection->fetchAllKeyValue('
                SELECT `base_class` AS `baseClass`, `author`
                FROM `plugin`
            ');
        } catch (Exception) {
            // to get it working in setup without a database connection
            $authors = [];
        }

        foreach ($this->kernel->getBundles() as $name => $bundle) {
            // skip Administration bundle because we are in the storefront scope
            if (!$bundle instanceof Bundle || $name === self::ADMINISTRATION_BUNDLE_NAME) {
                continue;
            }

            // skip plugin snippets that already exist via translation installation
            if ($bundle instanceof Plugin && $this->translationLoader->pluginTranslationExists($bundle)) {
                continue;
            }

            $snippetDir = $bundle->getPath() . '/Resources/snippet';

            if (!is_dir($snippetDir)) {
                continue;
            }

            foreach ($this->loadSnippetFilesInDir($snippetDir, $bundle, $authors) as $snippetFile) {
                if ($snippetFileCollection->hasFileForPath($snippetFile->getPath())) {
                    continue;
                }

                $snippetFileCollection->add($snippetFile);
            }
        }
    }

    private function loadAppSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            $snippetFiles = $this->appSnippetFileLoader->loadSnippetFilesFromApp($app['author'] ?? '', $app['path']);
            foreach ($snippetFiles as $snippetFile) {
                $snippetFile->setTechnicalName($app['name']);
                $snippetFileCollection->add($snippetFile);
            }
        }
    }

    /**
     * @param array<string, string> $authors
     *
     * @return AbstractSnippetFile[]
     */
    private function loadSnippetFilesInDir(string $snippetDir, Bundle $bundle, array $authors): array
    {
        $finder = new Finder();
        $finder->in($snippetDir)
            ->files()
            ->name('*.json');

        $snippetFiles = [];

        foreach ($finder->getIterator() as $fileInfo) {
            $nameParts = explode('.', $fileInfo->getFilenameWithoutExtension());

            $snippetFile = null;
            switch (\count($nameParts)) {
                case 2:
                    $snippetFile = new GenericSnippetFile(
                        implode('.', $nameParts),
                        $fileInfo->getPathname(),
                        $nameParts[1],
                        $this->getAuthorFromBundle($bundle, $authors),
                        false,
                        $bundle->getName(),
                    );

                    break;
                case 3:
                    $snippetFile = new GenericSnippetFile(
                        implode('.', [$nameParts[0], $nameParts[1]]),
                        $fileInfo->getPathname(),
                        $nameParts[1],
                        $this->getAuthorFromBundle($bundle, $authors),
                        $nameParts[2] === 'base',
                        $bundle->getName(),
                    );

                    break;
            }

            if ($snippetFile) {
                $snippetFiles[] = $snippetFile;
            }
        }

        return $snippetFiles;
    }

    /**
     * @param array<string, string> $authors
     */
    private function getAuthorFromBundle(Bundle $bundle, array $authors): string
    {
        if (!$bundle instanceof Plugin) {
            return 'Shopware';
        }

        return $authors[$bundle::class] ?? '';
    }
}

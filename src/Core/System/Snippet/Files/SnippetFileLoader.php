<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Kernel;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @description Loads storefront snippet files from the core, plugins, and apps into a SnippetFileCollection.
 */
#[Package('discovery')]
class SnippetFileLoader implements SnippetFileLoaderInterface
{
    public const SCOPE_PLATFORM = 'Platform';

    public const SCOPE_PLUGINS = 'Plugins';

    private const ADMINISTRATION_BUNDLE_NAME = 'Administration';

    /**
     * @internal
     */
    public function __construct(
        private readonly Kernel $kernel,
        private readonly Connection $connection,
        private readonly AppSnippetFileLoader $appSnippetFileLoader,
        private readonly ActiveAppsLoader $activeAppsLoader,
        private readonly TranslationConfig $config,
        private readonly AbstractTranslationLoader $translationLoader,
        private readonly FilesystemOperator $translationReader,
        private readonly StorefrontSnippetStorage $snippetStorage,
        private readonly FilesystemOperator $privateFilesystem,
    ) {
    }

    public function loadSnippetFilesIntoCollection(SnippetFileCollection $snippetFileCollection): void
    {
        // Load snippets placed in the private filesystem first, every other source overrides them
        $this->loadFilesystemSnippets($snippetFileCollection);
        // Load snippets from private translation system
        $this->loadTranslationSnippets($snippetFileCollection);
        // Load snippets from Shopware bundles and plugins
        $this->loadShippedSnippets($snippetFileCollection);
        // Load snippets from active apps
        $this->loadAppSnippets($snippetFileCollection);
    }

    /**
     * Files below `snippets/storefront/` in the private filesystem, see {@see FilesystemStorefrontSnippets}.
     * Inside a `<source>/` directory, that directory becomes author and technical name of the snippet file.
     * Files placed directly in the root get the author `custom` and their domain (`storefront` for
     * `storefront.de.json`) as technical name.
     */
    private function loadFilesystemSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        if (!$this->privateFilesystem->directoryExists(FilesystemStorefrontSnippets::DIRECTORY)) {
            return;
        }

        $files = $this->privateFilesystem
            ->listContents(FilesystemStorefrontSnippets::DIRECTORY, true)
            ->filter(static fn (StorageAttributes $node): bool => $node->isFile() && \str_ends_with($node->path(), '.json'));

        foreach ($files as $file) {
            $relativeDirectory = Path::makeRelative(Path::getDirectory($file->path()), FilesystemStorefrontSnippets::DIRECTORY);
            $source = explode('/', $relativeDirectory)[0];
            $nameParts = $this->parseSnippetFileName(Path::getFilenameWithoutExtension($file->path()));

            if ($source === '..' || $nameParts === null) {
                continue;
            }

            $author = $source !== '' ? $source : FilesystemStorefrontSnippets::ROOT_AUTHOR;
            $technicalName = $source !== '' ? $source : explode('.', $nameParts['name'])[0];

            $snippetFileCollection->add(new FilesystemSnippetFile(
                $nameParts['name'],
                $file->path(),
                $nameParts['iso'],
                $author,
                $nameParts['isBase'],
                $technicalName,
            ));
        }
    }

    private function loadTranslationSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        $exclude = $this->getInactivePluginNames();

        $localesBasePath = \mb_ltrim($this->translationLoader->getLocalesBasePath(), '/\\');

        // regular expression template that can be used for filtering or matching path parts
        $translationPathRegexpTemplate = '#^/?'
            . Path::join($localesBasePath, '(?P<locale>[a-zA-Z-0-9-_]+)', '(?P<component>%s)', '(?P<plugin>%s)')
            . '.*$#';

        $excludedPathsRegexp = array_map(
            static fn (string $path) => \sprintf($translationPathRegexpTemplate, self::SCOPE_PLUGINS, $path),
            $exclude
        );

        $excludedLocalesPattern = $this->getExcludedLocalesPatternFromConfig($localesBasePath);
        if ($excludedLocalesPattern !== null) {
            $excludedPathsRegexp[] = $excludedLocalesPattern;
        }

        $translationFiles = $this->translationReader
            ->listContents($localesBasePath, true)
            ->filter(static fn (StorageAttributes $node) => $node->isFile())
            ->filter(static fn (StorageAttributes $node) => \str_ends_with($node->path(), '.json'))
            ->filter(static fn (StorageAttributes $node) => \preg_filter($excludedPathsRegexp, 'EXCLUDED', $node->path()) !== 'EXCLUDED');

        $isPluginPathCheckRegexp = \sprintf($translationPathRegexpTemplate, self::SCOPE_PLATFORM . '|' . self::SCOPE_PLUGINS, '');
        foreach ($translationFiles as $translationFile) {
            \preg_match($isPluginPathCheckRegexp, $translationFile->path(), $pathComponents);

            // Check if the path matches the expected structure. If not, the directory was modified and the file should be skipped.
            $validityCheck = \array_intersect_key($pathComponents, array_fill_keys(['locale', 'component'], true));
            if (\count($validityCheck) !== 2 || $pathComponents['locale'] === '' || $pathComponents['component'] === '') {
                continue;
            }

            $technicalName = self::SCOPE_PLATFORM;
            if ($pathComponents['component'] === self::SCOPE_PLUGINS) {
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

    private function loadShippedSnippets(SnippetFileCollection $snippetFileCollection): void
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

            $snippetDir = $bundle->getPath() . '/Resources/snippet';

            if (!is_dir($snippetDir)) {
                continue;
            }

            foreach ($this->loadSnippetFilesInDir($snippetDir, $bundle, $authors) as $snippetFile) {
                if ($snippetFileCollection->hasFileForPath($snippetFile->getPath())) {
                    continue;
                }

                // skip plugin file if a core translation for this specific locale already exists
                if (
                    $bundle instanceof Plugin
                    && $this->translationLoader->pluginTranslationExistsForLocale($bundle, $snippetFile->getIso())
                ) {
                    continue;
                }

                $snippetFileCollection->add($snippetFile);
            }
        }
    }

    private function loadAppSnippets(SnippetFileCollection $snippetFileCollection): void
    {
        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            $directory = $this->snippetStorage->directory($app['name'], $app['version']);

            if ($directory === null) {
                continue;
            }

            foreach ($this->appSnippetFileLoader->loadSnippetFilesFromApp($app['author'] ?? '', $directory, true) as $snippetFile) {
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
            $nameParts = $this->parseSnippetFileName($fileInfo->getFilenameWithoutExtension());
            if ($nameParts === null) {
                continue;
            }

            $snippetFiles[] = new GenericSnippetFile(
                $nameParts['name'],
                $fileInfo->getPathname(),
                $nameParts['iso'],
                $this->getAuthorFromBundle($bundle, $authors),
                $nameParts['isBase'],
                $bundle->getName(),
            );
        }

        return $snippetFiles;
    }

    /**
     * Snippet files are named `<name>.<iso>.json` or `<name>.<iso>.base.json`.
     *
     * @return array{name: string, iso: string, isBase: bool}|null
     */
    private function parseSnippetFileName(string $filenameWithoutExtension): ?array
    {
        $nameParts = explode('.', $filenameWithoutExtension);

        return match (\count($nameParts)) {
            2 => ['name' => implode('.', $nameParts), 'iso' => $nameParts[1], 'isBase' => false],
            3 => ['name' => $nameParts[0] . '.' . $nameParts[1], 'iso' => $nameParts[1], 'isBase' => $nameParts[2] === 'base'],
            default => null,
        };
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

    private function getExcludedLocalesPatternFromConfig(string $path): ?string
    {
        $excludedLocales = $this->config->excludedLocales;

        if ($excludedLocales === []) {
            return null;
        }

        $localePattern = implode('|', $excludedLocales);

        return '#^/?' . Path::join($path, '(' . $localePattern . ')', '*') . '.*$#';
    }
}

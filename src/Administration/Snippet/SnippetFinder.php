<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Kernel;
use Shopware\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPath;
use Shopware\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPathCollection;
use Shopware\Core\System\Snippet\Files\FilesystemAdministrationSnippets;
use Shopware\Core\System\Snippet\Files\SnippetFileLoader;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 *
 * @description Loads administration snippets from the core, plugins, and apps.
 */
#[Package('discovery')]
class SnippetFinder implements SnippetFinderInterface
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public const ALLOWED_INTERSECTING_FIRST_LEVEL_SNIPPET_KEYS = [
        'sw-flow-custom-event',
    ];

    public function __construct(
        private readonly Kernel $kernel,
        private readonly Connection $connection,
        private readonly Filesystem $translationReader,
        private readonly FilesystemOperator $privateFilesystem,
        private readonly TranslationConfig $translationConfig,
        private readonly AbstractTranslationLoader $translationLoader,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function findSnippets(string $locale): array
    {
        $countryAgnosticSnippetFiles = $this->findSnippetFiles($locale, true);
        $countrySpecificSnippetFiles = $this->findSnippetFiles($locale);

        $countryAgnosticSnippets = $this->parseFiles($countryAgnosticSnippetFiles);
        $countrySpecificSnippets = $this->parseFiles($countrySpecificSnippetFiles);

        return array_replace_recursive(
            $this->getFilesystemSnippets($locale),
            $countryAgnosticSnippets,
            $countrySpecificSnippets,
            $this->getAppAdministrationSnippets($locale),
        );
    }

    /**
     * Snippets from the private filesystem, e.g. generated from legacy theme.json translations or
     * placed there by an integration. They form the lowest-priority layer, so every snippet file
     * shipped by the core, a plugin or an app overrides them.
     *
     * @return array<string, mixed>
     */
    private function getFilesystemSnippets(string $locale): array
    {
        if (!$this->privateFilesystem->directoryExists(FilesystemAdministrationSnippets::DIRECTORY)) {
            return [];
        }

        $language = explode('-', $locale)[0];
        $fileNames = [\sprintf('%s.json', $language), \sprintf('%s.json', $locale)];

        $paths = $this->privateFilesystem
            ->listContents(FilesystemAdministrationSnippets::DIRECTORY, true)
            ->filter(static fn (StorageAttributes $attributes): bool => $attributes->isFile() && \in_array(basename($attributes->path()), $fileNames, true))
            ->map(static fn (StorageAttributes $attributes): string => $attributes->path())
            ->toArray();

        // language files first, so a locale-specific file of the same source wins
        usort($paths, static function (string $a, string $b) use ($fileNames): int {
            $byName = array_search(basename($a), $fileNames, true) <=> array_search(basename($b), $fileNames, true);

            return $byName !== 0 ? $byName : strcmp($a, $b);
        });

        $snippets = [[]];
        foreach ($paths as $path) {
            $snippets[] = $this->decodeSnippetFile($path, $this->privateFilesystem->read($path));
        }

        return \array_replace_recursive(...$snippets);
    }

    private function findSnippetFiles(string $locale, bool $isBaseLanguage = false): SnippetPathCollection
    {
        if ($isBaseLanguage) {
            $locale = explode('-', $locale)[0];
        }

        $paths = new SnippetPathCollection();
        $this->addInstalledPlatformPaths($paths, $locale);

        if ($paths->isEmpty()) {
            $this->addShopwareCorePaths($paths);
        }

        $snippetNames = ['administration.json'];
        $snippetNames[] = \sprintf('%s.json', $locale);

        $this->addPluginPaths($paths, $locale);
        $this->addMeteorBundlePaths($paths);

        $localPaths = new SnippetPathCollection();
        $remotePaths = new SnippetPathCollection();

        foreach ($paths as $path) {
            if ($path->isLocal) {
                $localPaths->add($path);
            } else {
                $remotePaths->add($path);
            }
        }

        $snippetFiles = new SnippetPathCollection();
        array_map(
            static fn (string $path) => $snippetFiles->add(new SnippetPath($path, true)),
            $this->findLocalSnippetFiles($snippetNames, $localPaths),
        );
        array_map(
            static fn (string $path) => $snippetFiles->add(new SnippetPath($path)),
            $this->findRemoteSnippetFiles($snippetNames, $remotePaths),
        );

        return $snippetFiles;
    }

    private function addInstalledPlatformPaths(SnippetPathCollection $paths, string $locale): void
    {
        $path = $this->getValidatedLocalePath($locale);

        if ($path === null) {
            return;
        }

        $paths->add(new SnippetPath($path));
    }

    private function addPluginPaths(SnippetPathCollection $paths, string $locale): void
    {
        $activePlugins = $this->kernel->getPluginLoader()->getPluginInstances()->getActives();

        foreach ($activePlugins as $plugin) {
            $path = $this->getValidatedLocalePath($locale, $plugin);

            if ($path !== null) {
                $paths->add(new SnippetPath($path));

                continue;
            }

            // add the plugin specific paths if the translation does not exist
            $pluginPath = $plugin->getPath() . '/Resources/app/administration/src';

            if (\is_dir($pluginPath)) {
                $paths->add(new SnippetPath($pluginPath, true));
            }

            $meteorPluginPath = $plugin->getPath() . '/Resources/app/meteor-app';
            if (\is_dir($meteorPluginPath)) {
                $paths->add(new SnippetPath($meteorPluginPath, true));
            }
        }
    }

    private function getValidatedLocalePath(string $locale, ?Plugin $plugin = null): ?string
    {
        if (\in_array($locale, $this->translationConfig->excludedLocales, true)) {
            return null;
        }

        $path = $this->buildLocalePath($locale, $plugin);

        if (!$this->translationReader->directoryExists($path)) {
            return null;
        }

        return $path;
    }

    private function buildLocalePath(string $locale, ?Plugin $plugin = null): string
    {
        if ($plugin === null) {
            return Path::join($this->translationLoader->getLocalePath($locale), SnippetFileLoader::SCOPE_PLATFORM);
        }

        $name = $this->translationConfig->getMappedPluginName($plugin);

        return Path::join($this->translationLoader->getLocalePath($locale), SnippetFileLoader::SCOPE_PLUGINS, $name);
    }

    private function addMeteorBundlePaths(SnippetPathCollection $paths): void
    {
        $plugins = $this->kernel->getPluginLoader()->getPluginInstances()->all();
        $bundles = $this->kernel->getBundles();

        foreach ($bundles as $bundle) {
            if (\in_array($bundle, $plugins, true)) {
                continue;
            }

            $meteorBundlePath = $bundle->getPath() . '/Resources/app/meteor-app';

            // Add the meteor bundle path if it exists
            if (!\is_dir($meteorBundlePath)) {
                continue;
            }

            $paths->add(new SnippetPath($meteorBundlePath, true));
        }
    }

    private function addShopwareCorePaths(SnippetPathCollection $paths): void
    {
        $plugins = $this->kernel->getPluginLoader()->getPluginInstances()->all();
        $bundles = $this->kernel->getBundles();

        foreach ($bundles as $bundle) {
            if (\in_array($bundle, $plugins, true)) {
                continue;
            }

            if ($bundle->getName() === 'Administration') {
                $paths->add(new SnippetPath($bundle->getPath() . '/Resources/app/administration/src/app/snippet', true));
                $paths->add(new SnippetPath($bundle->getPath() . '/Resources/app/administration/src/module/*/snippet', true));
                $paths->add(new SnippetPath($bundle->getPath() . '/Resources/app/administration/src/app/component/*/*/snippet', true));

                continue;
            }

            if ($bundle->getName() === 'Storefront') {
                $paths->add(new SnippetPath($bundle->getPath() . '/Resources/app/administration/src/app/snippet', true));
                $paths->add(new SnippetPath($bundle->getPath() . '/Resources/app/administration/src/modules/*/snippet', true));

                continue;
            }

            $bundlePath = $bundle->getPath() . '/Resources/app/administration/src';
            $meteorBundlePath = $bundle->getPath() . '/Resources/app/meteor-app';

            // Add the bundle path if it exists
            if (\is_dir($bundlePath)) {
                $paths->add(new SnippetPath($bundlePath, true));
            }

            // Add the meteor bundle path if it exists
            if (\is_dir($meteorBundlePath)) {
                $paths->add(new SnippetPath($meteorBundlePath, true));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFiles(SnippetPathCollection $files): array
    {
        $localTranslationReader = new SymfonyFilesystem();
        $snippets = [[]];

        foreach ($files as $file) {
            if ($file->isLocal) {
                $content = $localTranslationReader->readFile($file->location);
            } else {
                $content = $this->translationReader->read($file->location);
            }

            $snippets[] = $this->decodeSnippetFile($file->location, $content);
        }

        $snippets = \array_replace_recursive(...$snippets);
        \ksort($snippets);

        return $snippets;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSnippetFile(string $location, string $content): array
    {
        if ($content === '') {
            return [];
        }

        try {
            return \json_decode($content, true, 512, \JSON_THROW_ON_ERROR) ?? [];
        } catch (\JsonException $e) {
            if ($this->debug) {
                throw SnippetException::invalidSnippetFile($location, $e);
            }

            // a single broken snippet file (e.g. from a plugin) must not take down the whole administration
            $this->logger->error(
                \sprintf('The administration snippet file "%s" is invalid and was skipped: %s', $location, $e->getMessage()),
                ['exception' => $e]
            );
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getAppAdministrationSnippets(string $locale): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT app_administration_snippet.value
             FROM locale
             INNER JOIN app_administration_snippet ON locale.id = app_administration_snippet.locale_id
             INNER JOIN app ON app_administration_snippet.app_id = app.id
             WHERE locale.code = :code AND app.active = 1;',
            ['code' => $locale]
        );

        $decodedSnippets = \array_map(
            static fn ($data) => \json_decode((string) $data['value'], true, 512, \JSON_THROW_ON_ERROR),
            $result
        );

        $appSnippets = \array_replace_recursive([], ...$decodedSnippets);

        return $this->sanitizeAppSnippets($appSnippets);
    }

    /**
     * @param array<string, mixed> $snippets
     *
     * @return array<string, mixed>
     */
    private function sanitizeAppSnippets(array $snippets): array
    {
        $sanitizedSnippets = [];
        foreach ($snippets as $key => $value) {
            if (\is_string($value)) {
                $sanitizedSnippets[$key] = $this->htmlSanitizer->sanitize($value);

                continue;
            }

            if (\is_array($value)) {
                $sanitizedSnippets[$key] = $this->sanitizeAppSnippets($value);
            }
        }

        return $sanitizedSnippets;
    }

    /**
     * @param list<string> $snippetNames
     *
     * @return list<string>
     */
    private function findLocalSnippetFiles(array $snippetNames, SnippetPathCollection $paths): array
    {
        if ($paths->isEmpty()) {
            return [];
        }
        $files = [];
        $finder = (new Finder())
            ->files()
            ->exclude('node_modules')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->ignoreUnreadableDirs()
            ->name($snippetNames)
            ->in($paths->toLocationArray());

        foreach ($finder->getIterator() as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * @param list<string> $snippetNames
     *
     * @return list<string>
     */
    private function findRemoteSnippetFiles(array $snippetNames, SnippetPathCollection $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            $snippetPaths = \array_map(
                static fn (string $name) => Path::join($path->location, $name),
                $snippetNames
            );
            $existingSnippetNames = \array_filter(
                $snippetPaths,
                fn (string $snippetPath) => $this->translationReader->fileExists($snippetPath)
            );
            $files = \array_merge($files, $existingSnippetNames);
        }

        return $files;
    }
}

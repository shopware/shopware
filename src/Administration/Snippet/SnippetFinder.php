<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Kernel;
use Shopware\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPath;
use Shopware\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPathCollection;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
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
        private readonly TranslationConfig $translationConfig,
        private readonly TranslationLoader $translationLoader,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function findSnippets(string $locale): array
    {
        $snippetFiles = $this->findSnippetFiles($locale);
        $snippets = $this->parseFiles($snippetFiles);

        return [...$snippets, ...$this->getAppAdministrationSnippets($locale)];
    }

    private function findSnippetFiles(string $locale): SnippetPathCollection
    {
        $paths = new SnippetPathCollection();
        $this->addInstalledPlatformPaths($paths, $locale);

        if ($paths->isEmpty()) {
            // @deprecated tag:v6.8.0 - Will be removed and replaced with the new translation system.
            if (!Feature::isActive('v6.8.0.0')) {
                $this->addShopwareLegacyPaths($paths);
            }
        }

        $snippetNames = ['administration.json'];
        if (!Feature::isActive('v6.8.0.0')) {
            // @deprecated tag:v6.8.0 - Will be removed and replaced with the new translation system.
            $snippetNames[] = \sprintf('%s.json', $locale);
        }

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
            fn (string $path) => $snippetFiles->add(new SnippetPath($path, true)),
            $this->findLocalSnippetFiles($snippetNames, $localPaths),
        );
        array_map(
            fn (string $path) => $snippetFiles->add(new SnippetPath($path)),
            $this->findRemoteSnippetFiles($snippetNames, $remotePaths),
        );

        return $snippetFiles;
    }

    private function addInstalledPlatformPaths(SnippetPathCollection $paths, string $locale): void
    {
        $path = Path::join($this->translationLoader->getLocalePath($locale), 'Platform');

        if (!$this->translationReader->directoryExists($path)) {
            return;
        }

        $paths->add(new SnippetPath($path));
    }

    private function addPluginPaths(SnippetPathCollection $paths, string $locale): void
    {
        $activePlugins = $this->kernel->getPluginLoader()->getPluginInstances()->getActives();

        foreach ($activePlugins as $plugin) {
            $name = $this->translationConfig->getMappedPluginName($plugin);
            $path = Path::join($this->translationLoader->getLocalePath($locale), 'Plugins', $name);

            // add the path of the installed plugin translation if it exists
            if ($this->translationReader->directoryExists($path)) {
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

    /**
     * @deprecated tag:v6.8.0 - Will be removed and replaced with the new translation system.
     * The method `getInstalledSnippetPaths` will be used to fetch the paths.
     */
    private function addShopwareLegacyPaths(SnippetPathCollection $paths): void
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
            if (!empty($content)) {
                $snippets[] = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR) ?? [];
            }
        }

        $snippets = \array_replace_recursive(...$snippets);
        \ksort($snippets);

        return $snippets;
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
            fn ($data) => \json_decode((string) $data['value'], true, 512, \JSON_THROW_ON_ERROR),
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
        $sanitizer = new HtmlSanitizer();

        $sanitizedSnippets = [];
        foreach ($snippets as $key => $value) {
            if (\is_string($value)) {
                $sanitizedSnippets[$key] = $sanitizer->sanitize($value);

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
                fn (string $name) => Path::join($path->location, $name),
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

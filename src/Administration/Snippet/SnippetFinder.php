<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Kernel;
use Shopware\Core\System\Snippet\Service\TranslationConfigLoader;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Struct\SnippetPaths;
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

    /**
     * @return array<int, string>
     */
    private function findSnippetFiles(string $locale): array
    {
        $paths = new SnippetPaths();
        $this->addInstalledPlatformPaths($paths, $locale);

        if ($paths->empty()) {
            // @deprecated tag:v6.8.0 - Will be removed and replaced with the new translation system.
            $this->addShopwareLegacyPaths($paths);
        }

        $this->addPluginPaths($paths, $locale);
        $this->addMeteorBundlePaths($paths);

        $finder = (new Finder())
            ->files()
            ->exclude('node_modules')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->ignoreUnreadableDirs()
            ->name([
                'administration.json',
                \sprintf('%s.json', $locale), // @deprecated tag:v6.8.0 - Will be removed and replaced with the new translation system.
            ])
            ->in($paths->all());

        $iterator = $finder->getIterator();
        $files = [];

        foreach ($iterator as $file) {
            $files[] = $file->getRealPath();
        }

        return \array_unique($files);
    }

    private function addInstalledPlatformPaths(SnippetPaths $paths, string $locale): void
    {
        $path = \sprintf(TranslationLoader::TRANSLATION_DESTINATION . '/%s/Platform', $locale);

        if (!\is_dir($path)) {
            return;
        }

        $paths->add($path);
    }

    private function addPluginPaths(SnippetPaths $paths, string $locale): void
    {
        $activePlugins = $this->kernel->getPluginLoader()->getPluginInstances()->getActives();

        foreach ($activePlugins as $plugin) {
            $name = TranslationConfigLoader::getMappedPluginName($plugin);
            $path = \sprintf(TranslationLoader::TRANSLATION_DESTINATION . '/%s/Plugins/%s', $locale, $name);

            // add the path of the installed plugin translation if it exists
            if (\is_dir($path)) {
                $paths->add($path);

                continue;
            }

            // add the plugin specific paths if the translation does not exist
            $pluginPath = $plugin->getPath() . '/Resources/app/administration/src';

            if (\is_dir($pluginPath)) {
                $paths->add($pluginPath);
            }

            $meteorPluginPath = $plugin->getPath() . '/Resources/app/meteor-app';
            if (\is_dir($meteorPluginPath)) {
                $paths->add($meteorPluginPath);
            }
        }
    }

    private function addMeteorBundlePaths(SnippetPaths $paths): void
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

            $paths->add($meteorBundlePath);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed and replaced with the new translation system.
     * The method `getInstalledSnippetPaths` will be used to fetch the paths.
     */
    private function addShopwareLegacyPaths(SnippetPaths $paths): void
    {
        $plugins = $this->kernel->getPluginLoader()->getPluginInstances()->all();
        $bundles = $this->kernel->getBundles();

        foreach ($bundles as $bundle) {
            if (\in_array($bundle, $plugins, true)) {
                continue;
            }

            if ($bundle->getName() === 'Administration') {
                $paths->merge([
                    $bundle->getPath() . '/Resources/app/administration/src/app/snippet',
                    $bundle->getPath() . '/Resources/app/administration/src/module/*/snippet',
                    $bundle->getPath() . '/Resources/app/administration/src/app/component/*/*/snippet',
                ]);

                continue;
            }

            if ($bundle->getName() === 'Storefront') {
                $paths->merge([
                    $bundle->getPath() . '/Resources/app/administration/src/app/snippet',
                    $bundle->getPath() . '/Resources/app/administration/src/modules/*/snippet',
                ]);

                continue;
            }

            $bundlePath = $bundle->getPath() . '/Resources/app/administration/src';
            $meteorBundlePath = $bundle->getPath() . '/Resources/app/meteor-app';

            // Add the bundle path if it exists
            if (\is_dir($bundlePath)) {
                $paths->add($bundlePath);
            }

            // Add the meteor bundle path if it exists
            if (\is_dir($meteorBundlePath)) {
                $paths->add($meteorBundlePath);
            }
        }
    }

    /**
     * @param array<int, string> $files
     *
     * @return array<string, mixed>
     */
    private function parseFiles(array $files): array
    {
        $snippets = [[]];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $snippets[] = json_decode($content, true, 512, \JSON_THROW_ON_ERROR) ?? [];
            }
        }

        $snippets = array_replace_recursive(...$snippets);
        ksort($snippets);

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

        $decodedSnippets = array_map(
            fn ($data) => json_decode((string) $data['value'], true, 512, \JSON_THROW_ON_ERROR),
            $result
        );

        $appSnippets = array_replace_recursive([], ...$decodedSnippets);

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
}

<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Kernel;
use Symfony\Component\Finder\Finder;

/**
 * @internal
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
        private readonly Connection $connection
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function findSnippets(string $locale): array
    {
        $snippetFiles = $this->findSnippetFiles($locale);
        $snippets = $this->parseFiles($snippetFiles);

        $snippets = [...$snippets, ...$this->getAppAdministrationSnippets($locale, $snippets)];

        if (!\count($snippets)) {
            return [];
        }

        return $snippets;
    }

    /**
     * @return array<int, string>
     */
    private function getBundlePaths(): array
    {
        $plugins = $this->kernel->getPluginLoader()->getPluginInstances()->all();
        $activePlugins = $this->kernel->getPluginLoader()->getPluginInstances()->getActives();
        $bundles = $this->kernel->getBundles();
        $paths = [];

        foreach ($activePlugins as $plugin) {
            $pluginPath = $plugin->getPath() . '/Resources/app/administration/src';
            if (file_exists($pluginPath)) {
                $paths[] = $pluginPath;
            }

            $meteorPluginPath = $plugin->getPath() . '/Resources/app/meteor-app';
            if (file_exists($meteorPluginPath)) {
                $paths[] = $meteorPluginPath;
            }
        }

        foreach ($bundles as $bundle) {
            if (\in_array($bundle, $plugins, true)) {
                continue;
            }

            if ($bundle->getName() === 'Administration') {
                $paths = array_merge($paths, [
                    $bundle->getPath() . '/Resources/app/administration/src/app/snippet',
                    $bundle->getPath() . '/Resources/app/administration/src/module/*/snippet',
                    $bundle->getPath() . '/Resources/app/administration/src/app/component/*/*/snippet',
                ]);

                continue;
            }

            if ($bundle->getName() === 'Storefront') {
                $paths = array_merge($paths, [
                    $bundle->getPath() . '/Resources/app/administration/src/app/snippet',
                    $bundle->getPath() . '/Resources/app/administration/src/modules/*/snippet',
                ]);

                continue;
            }

            $bundlePath = $bundle->getPath() . '/Resources/app/administration/src';
            $meteorBundlePath = $bundle->getPath() . '/Resources/app/meteor-app';

            // Add the bundle path if it exists
            if (file_exists($bundlePath)) {
                $paths[] = $bundlePath;
            }

            // Add the meteor bundle path if it exists
            if (file_exists($meteorBundlePath)) {
                $paths[] = $meteorBundlePath;
            }
        }

        return $paths;
    }

    /**
     * @return array<int, string>
     */
    private function findSnippetFiles(string $locale): array
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
     * @param array<string, mixed> $existingSnippets
     *
     * @return array<string, mixed>
     */
    private function getAppAdministrationSnippets(string $locale, array $existingSnippets): array
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
        $appSnippets = $this->sanitizeAppSnippets($appSnippets);

        return $appSnippets;
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

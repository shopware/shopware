<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Kernel;
use Symfony\Component\Finder\Finder;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - Will be internal in v6.7.0
 */
#[Package('administration')]
class SnippetFinder implements SnippetFinderInterface
{
    /**
     * @internal
     */
    public const ALLOWED_INTERSECTING_FIRST_LEVEL_SNIPPET_KEYS = [
        'sw-flow-custom-event',
    ];

    /**
     * @internal
     */
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
    private function getPluginPaths(): array
    {
        $plugins = $this->kernel->getPluginLoader()->getPluginInstances()->all();
        $activePlugins = $this->kernel->getPluginLoader()->getPluginInstances()->getActives();
        $bundles = $this->kernel->getBundles();
        $paths = [];

        foreach ($activePlugins as $plugin) {
            $pluginPath = $plugin->getPath() . '/Resources/app/administration';
            if (!file_exists($pluginPath)) {
                continue;
            }

            $paths[] = $pluginPath;
        }

        foreach ($bundles as $bundle) {
            if (\in_array($bundle, $plugins, true)) {
                continue;
            }

            $bundlePath = $bundle->getPath() . '/Resources/app/administration';

            if (!file_exists($bundlePath)) {
                continue;
            }

            $paths[] = $bundlePath;
        }

        return $paths;
    }

    /**
     * @return array<int, string>
     */
    private function findSnippetFiles(string $locale, bool $withPlugins = true): array
    {
        $finder = (new Finder())
            ->files()
            ->in(__DIR__ . '/../../*/Resources/app/administration/src/')
            ->exclude('node_modules')
            ->ignoreUnreadableDirs();
        $finder->name(sprintf('%s.json', $locale));

        if ($withPlugins) {
            $finder->in($this->getPluginPaths());
        }

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

        $this->validateAppSnippets($existingSnippets, $appSnippets);

        return $appSnippets;
    }

    /**
     * @param array<string, mixed> $existingSnippets
     * @param array<string, mixed> $appSnippets
     */
    private function validateAppSnippets(array $existingSnippets, array $appSnippets): void
    {
        $existingSnippetKeys = array_keys($existingSnippets);
        $appSnippetKeys = array_keys($appSnippets);
        $duplicatedKeys = $this->getInvalidIntersections($existingSnippetKeys, $appSnippetKeys);

        if (!empty($duplicatedKeys)) {
            throw SnippetException::duplicatedFirstLevelKey($duplicatedKeys);
        }
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
     * @param list<string> $snippetKeys
     * @param list<string> $additionalSnippetKeys
     *
     * @return list<string>
     */
    private function getInvalidIntersections(array $snippetKeys, array $additionalSnippetKeys): array
    {
        $intersections = array_intersect($snippetKeys, $additionalSnippetKeys);

        if (empty($intersections)) {
            return [];
        }

        return array_values(array_filter(
            $intersections,
            fn ($key) => !\in_array($key, self::ALLOWED_INTERSECTING_FIRST_LEVEL_SNIPPET_KEYS, true)
        ));
    }
}

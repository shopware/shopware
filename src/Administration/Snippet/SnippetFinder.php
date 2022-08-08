<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Shopware\Administration\Snippet\Exception\DuplicateAppSnippetKeysException;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Kernel;
use Symfony\Component\Finder\Finder;

class SnippetFinder implements SnippetFinderInterface
{
    private Kernel $kernel;

    private ActiveAppsLoader $activeAppsLoader;

    private string $projectDir;

    /**
     * @internal
     */
    public function __construct(Kernel $kernel, ActiveAppsLoader $activeAppsLoader)
    {
        $this->kernel = $kernel;
        $this->activeAppsLoader = $activeAppsLoader;

        $this->projectDir = $this->kernel->getContainer()->getParameter('kernel.project_dir');
    }

    /**
     * @return array<string|array>
     */
    public function findSnippets(string $locale): array
    {
        $snippetFiles = $this->findSnippetFiles($locale);

        if (!\count($snippetFiles)) {
            return [];
        }

        return $this->parseFiles($snippetFiles);
    }

    /**
     * @return array<string>
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
     * @return array<string>
     */
    private function getAppPaths(): array
    {
        $apps = $this->activeAppsLoader->getActiveApps();

        $paths = [];
        foreach ($apps as $app) {
            $appPath = sprintf('%s/%s/Resources/app/administration/snippet', $this->projectDir, $app['path']);
            if (!file_exists($appPath)) {
                continue;
            }

            $paths[] = $appPath;
        }

        return $paths;
    }

    /**
     * @return array<string>
     */
    private function findSnippetFiles(?string $locale = null, bool $withPlugins = true, bool $withApps = true): array
    {
        $finder = (new Finder())
            ->files()
            ->in(__DIR__ . '/../../*/Resources/app/administration/src/')
            ->exclude('node_modules')
            ->ignoreUnreadableDirs();

        if ($locale) {
            $finder->name(sprintf('%s.json', $locale));
        } else {
            $finder->name('/[a-z]{2}-[A-Z]{2}\.json/');
        }

        if ($withPlugins) {
            $finder->in($this->getPluginPaths());
        }

        if ($withApps) {
            $finder->in($this->getAppPaths());
        }

        $iterator = $finder->getIterator();
        $files = [];

        foreach ($iterator as $file) {
            $files[] = $file->getRealPath();
        }

        return \array_unique($files);
    }

    /**
     * @return array<string|array>
     */
    private function parseFiles(array $files): array
    {
        $appsPath = sprintf('%s/custom/apps', $this->projectDir);
        $snippets = [[]];
        $appSnippets = [];

        foreach ($files as $file) {
            if (is_file($file) === false) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content !== false) {
                $currentSnippetFile = json_decode($content, true) ?? [];

                $startWithAppsPath = substr($file, 0, \strlen($appsPath)) === $appsPath;
                if ($startWithAppsPath) {
                    $appSnippets[$file] = $currentSnippetFile;

                    continue;
                }

                $snippets[] = $currentSnippetFile;
            }
        }

        $snippets = array_replace_recursive(...$snippets);

        if (\count($appSnippets) > 0) {
            $this->validateAppSnippets($snippets, $appSnippets);
            $appSnippets = array_replace_recursive(...array_values($appSnippets));
            $appSnippets = $this->sanitizeAppSnippets($appSnippets);
            $snippets = array_merge($snippets, $appSnippets);
        }

        ksort($snippets);

        return $snippets;
    }

    /**
     * @param array<string|array> $coreSnippets
     * @param array<string|array> $appSnippetFiles
     *
     * @throws DuplicateAppSnippetKeysException
     */
    private function validateAppSnippets(array $coreSnippets, array $appSnippetFiles): void
    {
        $coreKeys = array_keys($coreSnippets);

        $invalidEntries = [];
        foreach ($appSnippetFiles as $filePath => $fileSnippets) {
            $invalidEntriesPerFile = [];
            foreach (array_keys($fileSnippets) as $key) {
                if (\in_array($key, $coreKeys)) {
                    $invalidEntriesPerFile[] = $key;
                }
            }
            if (\count($invalidEntriesPerFile) > 0) {
                $invalidEntries[$filePath] = $invalidEntriesPerFile;
            }
        }

        if (\count($invalidEntries) > 0) {
            throw new DuplicateAppSnippetKeysException($invalidEntries);
        }
    }

    /**
     * @param array<string|array> $snippets
     *
     * @return array<string|array>
     */
    private function sanitizeAppSnippets(array $snippets): array
    {
        $sanitizer = new HtmlSanitizer();

        $sanitizedSnippets = [];
        foreach($snippets as $key => $value) {
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

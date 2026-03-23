<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Component;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Visibility;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;

/**
 * Publishes built component JS and CSS files from each bundle's
 * `Resources/app/storefront/dist-es/components/` into the shared
 * `public/components/` directory so they can be served at fixed,
 * theme-agnostic URLs.
 *
 * After publishing it writes `var/cache/component-manifest.json` — a map
 * of component tag → { js, css } URL paths that ThemeCompiler reads to
 * build the runtime import map and CSS list without re-copying files on
 * every `theme:compile`.
 *
 * The `css` value is always a list of public URLs (in source order, deduped):
 * one entry per stylesheet associated with the component. Sources that can
 * contribute a stylesheet are the component's sibling `.scss` or `.css` file
 * plus any CSS that Vite attached to the component's JS entry (e.g. side-
 * effect imports of `*.scss` / `*.css` from third-party packages). The `css`
 * key is omitted entirely when no CSS was emitted for that component.
 */
#[Package('framework')]
class ComponentPublisher
{
    /**
     * Path inside the public filesystem where component assets are stored.
     * Maps to `public/components/` on disk.
     */
    public const PUBLIC_COMPONENTS_DIR = 'components';

    /**
     * Path inside the temp filesystem where the combined manifest is written.
     * Maps to `var/cache/component-manifest.json` on disk.
     */
    public const MANIFEST_PATH = 'cache/component-manifest.json';

    private const DIST_ES_COMPONENTS = 'Resources/app/storefront/dist-es/components';
    private const VITE_MANIFEST = '.vite/manifest.json';

    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $publicFilesystem,
        private readonly FilesystemOperator $tempFilesystem,
        private readonly string $projectDir,
        private readonly string $visibility = Visibility::PUBLIC,
        private readonly LocalFilesystem $localFilesystem = new LocalFilesystem(),
    ) {
    }

    /**
     * Publishes component assets for every bundle listed in `var/plugins.json`
     * that has a `dist-es/components/` Vite build output.
     *
     * Called during deployment (via `storefront:publish-components --all`) to
     * seed the public/components/ directory from scratch.
     */
    public function publishAll(): void
    {
        $plugins = $this->readPluginsJson();

        if ($plugins === null) {
            return;
        }

        $this->cleanupPublicComponentsDirectory();

        $manifest = [];

        foreach ($plugins as $bundleName => $config) {
            $bundleAbsPath = $this->projectDir . '/' . ltrim((string) ($config['basePath'] ?? ''), '/');
            $bundleManifest = $this->publishBundleInternal($bundleAbsPath, (string) $bundleName);
            $manifest = array_merge($manifest, $bundleManifest);
        }

        $this->writeComponentManifest($manifest);
    }

    /**
     * Publishes component assets for a single bundle identified by its
     * `var/plugins.json` key.
     *
     * @return bool|null Returns `true` when entries were written,
     * `false` when the bundle exists but no entries were produced,
     * and `null` when the bundle is not listed in `var/plugins.json`.
     */
    public function publishBundleByName(string $bundleName): ?bool
    {
        $plugins = $this->readPluginsJson();

        if ($plugins === null || !isset($plugins[$bundleName])) {
            return null;
        }

        $bundleAbsPath = $this->projectDir . '/' . ltrim((string) ($plugins[$bundleName]['basePath'] ?? ''), '/');

        return $this->publishBundle($bundleAbsPath, $bundleName);
    }

    /**
     * Clears `public/components/` before a full republish to avoid stale files
     * from previous builds (old hashes, removed components, removed bundles).
     */
    private function cleanupPublicComponentsDirectory(): void
    {
        try {
            if ($this->publicFilesystem->directoryExists(self::PUBLIC_COMPONENTS_DIR)) {
                $this->publicFilesystem->deleteDirectory(self::PUBLIC_COMPONENTS_DIR);
            }
        } catch (FilesystemException) {
            // Best-effort cleanup: publishing continues and overwrites active files.
        }
    }

    /**
     * Publishes component assets for a single bundle and merges the result
     * into the existing component manifest.
     *
     * Called by the plugin/app lifecycle subscribers when a bundle is
     * activated or updated.
     */
    public function publishBundle(string $bundleAbsPath, string $bundleName): bool
    {
        $newEntries = $this->publishBundleInternal($bundleAbsPath, $bundleName);

        if ($newEntries === []) {
            return false;
        }

        $existing = $this->readComponentManifest();
        $merged = array_merge($existing, $newEntries);

        if ($merged === $existing) {
            return false;
        }

        $this->writeComponentManifest($merged);

        return true;
    }

    /**
     * Removes all published assets for the given bundle and regenerates the
     * component manifest without those entries.
     *
     * Called by the plugin/app lifecycle subscribers when a bundle is
     * deactivated or uninstalled.
     */
    public function unpublish(string $bundleName): bool
    {
        // Remove the bundle's subdirectory from public/components/.
        // Core Storefront uses bare component paths (e.g. Sw/…); extensions
        // use a namespace-prefixed path (e.g. ComponentTestApp/…) matching the
        // bundle name.
        $publicDir = self::PUBLIC_COMPONENTS_DIR . '/' . $bundleName;
        try {
            if ($this->publicFilesystem->directoryExists($publicDir)) {
                $this->publicFilesystem->deleteDirectory($publicDir);
            }
        } catch (FilesystemException) {
            // Best-effort: directory may already be absent.
        }

        // Regenerate the manifest without this bundle's entries.
        $existing = $this->readComponentManifest();
        $filtered = array_filter(
            $existing,
            static fn (string $tag): bool => !str_starts_with($tag, $bundleName . ':'),
            \ARRAY_FILTER_USE_KEY,
        );

        if ($filtered === $existing) {
            return false;
        }

        $this->writeComponentManifest($filtered);

        return true;
    }

    /**
     * Builds scoped import map entries for extension vendor chunks.
     *
     * Each extension bundle that has published a vendor-map.json gets a scope
     * entry so that vendor-specifier imports are resolved to the correct
     * content-hashed chunk when loading modules inside that extension's scope.
     *
     * Core Storefront vendor chunks are top-level imports handled separately
     * by ThemeCompiler (it already knows the core storefrontJsDir).
     *
     * @return array<string, array<string, string>>  scopeKey → [specifier → chunkUrl]
     */
    public function buildExtensionVendorScopes(string $publicBaseUrl): array
    {
        $plugins = $this->readPluginsJson();

        if ($plugins === null) {
            return [];
        }

        $publicBaseUrl = rtrim($publicBaseUrl, '/');
        $scopes = [];

        foreach ($plugins as $bundleName => $config) {
            if ($bundleName === 'Storefront') {
                continue;
            }

            $bundleStorefrontDir = $this->projectDir . '/' . ltrim((string) ($config['basePath'] ?? ''), '/')
                . self::DIST_ES_COMPONENTS;
            $vendorMapPath = $bundleStorefrontDir . '/' . self::VITE_MANIFEST;
            $vendorMapPath = str_replace('manifest.json', 'vendor-map.json', $vendorMapPath);

            if (!$this->localFilesystem->exists($vendorMapPath)) {
                continue;
            }

            try {
                /** @var array<string, string>|null $vendorMap */
                $vendorMap = json_decode(
                    $this->localFilesystem->readFile($vendorMapPath),
                    true,
                    512,
                    \JSON_THROW_ON_ERROR,
                );
            } catch (IOException|\JsonException) {
                continue;
            }

            if (!\is_array($vendorMap) || $vendorMap === []) {
                continue;
            }

            $scopeKey = $publicBaseUrl . '/components/' . $bundleName . '/';
            foreach ($vendorMap as $specifier => $chunkPath) {
                $scopes[$scopeKey][$specifier] = $publicBaseUrl . '/components/' . $chunkPath;
            }
        }

        return $scopes;
    }

    /**
     * Returns the current component manifest or an empty array when none exists.
     *
     * @return array<string, array{js?: string, css?: list<string>}>
     */
    public function readComponentManifest(): array
    {
        try {
            if (!$this->tempFilesystem->fileExists(self::MANIFEST_PATH)) {
                return [];
            }

            /** @var array<string, array{js?: string, css?: list<string>}> $data */
            $data = json_decode($this->tempFilesystem->read(self::MANIFEST_PATH), true, 512, \JSON_THROW_ON_ERROR);

            return \is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Copies all files emitted by Vite for a single bundle into public/components/
     * and returns the manifest entries produced.
     *
     * The list of files to copy is derived from the bundle's `.vite/manifest.json`
     * (every `file` and every `css[]` path) rather than from a directory walk.
     * This has two benefits:
     *  1. The copy is restricted to files the current build actually emitted,
     *     avoiding stale leftovers from a previous build.
     *  2. The whole operation goes through the injected filesystems, so it is
     *     fully testable without any native `file_get_contents` / `Finder` calls.
     *
     * @return array<string, array{js?: string, css?: list<string>}>
     */
    private function publishBundleInternal(string $bundleAbsPath, string $bundleName): array
    {
        $bundleAbsPath = $this->normalizeBundlePath($bundleAbsPath);
        $distDir = $this->resolveDistDir($bundleAbsPath);

        if ($distDir === null) {
            return [];
        }

        $viteManifestPath = $distDir . '/' . self::VITE_MANIFEST;

        try {
            /** @var array<string, array{file?: string, name?: string, src?: string, isEntry?: bool, css?: list<string>}> $viteManifest */
            $viteManifest = json_decode(
                $this->localFilesystem->readFile($viteManifestPath),
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );
        } catch (IOException|\JsonException) {
            return [];
        }

        foreach ($this->collectOutputFiles($viteManifest) as $relative) {
            $sourcePath = $distDir . '/' . $relative;
            $targetPath = self::PUBLIC_COMPONENTS_DIR . '/' . $relative;

            try {
                $content = $this->localFilesystem->readFile($sourcePath);
            } catch (IOException) {
                // Skip missing files referenced by the manifest — a broken build
                // shouldn't abort publishing of the remaining entries.
                continue;
            }

            try {
                $this->publicFilesystem->write($targetPath, $content, ['visibility' => $this->visibility]);
            } catch (FilesystemException) {
                continue;
            }
        }

        return $this->buildManifestEntries($viteManifest, $bundleName);
    }

    /**
     * Resolves the bundle's `dist-es/components` directory.
     *
     * Plugins are discovered by lifecycle events via their plugin root path
     * (`custom/plugins/Foo/`), while their bundle resources typically live in
     * `custom/plugins/Foo/src/Resources/...`. Apps and core bundles use the
     * bundle path directly. Support both layouts.
     */
    private function resolveDistDir(string $bundleAbsPath): ?string
    {
        $direct = $bundleAbsPath . '/' . self::DIST_ES_COMPONENTS;
        if ($this->localFilesystem->exists($direct . '/' . self::VITE_MANIFEST)) {
            return $direct;
        }

        $srcLayout = $bundleAbsPath . '/src/' . self::DIST_ES_COMPONENTS;
        if ($this->localFilesystem->exists($srcLayout . '/' . self::VITE_MANIFEST)) {
            return $srcLayout;
        }

        return null;
    }

    private function normalizeBundlePath(string $bundlePath): string
    {
        if (str_starts_with($bundlePath, '/')) {
            return rtrim($bundlePath, '/');
        }

        return rtrim($this->projectDir . '/' . ltrim($bundlePath, '/'), '/');
    }

    /**
     * Returns the de-duplicated set of output file paths (relative to `dist-es/components/`)
     * referenced by the Vite manifest: every entry's `file` plus every file listed
     * in any entry's `css[]` array.
     *
     * @param array<string, array{file?: string, css?: list<string>}> $viteManifest
     *
     * @return list<string>
     */
    private function collectOutputFiles(array $viteManifest): array
    {
        $files = [];
        foreach ($viteManifest as $entry) {
            if (isset($entry['file']) && $entry['file'] !== '') {
                $files[$entry['file']] = true;
            }
            if (isset($entry['css']) && \is_array($entry['css'])) {
                foreach ($entry['css'] as $cssFile) {
                    if (\is_string($cssFile) && $cssFile !== '') {
                        $files[$cssFile] = true;
                    }
                }
            }
        }

        return array_keys($files);
    }

    /**
     * Builds component tag → URL entries from an already-parsed Vite manifest.
     *
     * Vite manifest entry format (keys are source paths relative to Vite root):
     *   {
     *     "../../views/components/Button/Primary.js": {
     *       "file": "CustomApp/Button/Primary.js",
     *       "name": "CustomApp/Button/Primary",
     *       "src": "../../views/components/Button/Primary.js",
     *       "isEntry": true
     *     }
     *   }
     *
     * Three sources contribute CSS to a component:
     *   - SCSS entries whose `file` ends in `.css` (the canonical sibling
     *     `.scss` file pattern, compiled to CSS by Vite's SCSS handler).
     *   - Plain CSS entries whose `file` ends in `.css` (the sibling `.css`
     *     file pattern, routed through Vite's virtual-CSS-module shim; see
     *     the `plainCssShimPlugin` in the storefront build config).
     *   - JS entries that list one or more files in a `css` array — Vite puts
     *     side-effect CSS imports there, both for in-source `import './x.scss'`
     *     and for vendor packages that ship their own stylesheets.
     *
     * Both sources are merged into a single `list<string>` per tag, in
     * source-iteration order, deduplicated.
     *
     * @param array<string, array{file?: string, name?: string, src?: string, isEntry?: bool, css?: list<string>}> $viteManifest
     *
     * @return array<string, array{js?: string, css?: list<string>}>
     */
    private function buildManifestEntries(array $viteManifest, string $bundleName): array
    {
        // First pass: collect CSS files associated with each named JS entry via
        // the `css` array. Vite places stylesheets that originate from the JS
        // module graph (in-source `import './x.scss'`, vendor side-effect
        // imports of `*.css`, etc.) here rather than as standalone entries.
        /** @var array<string, list<string>> $jsToCssFiles  entry name → css file paths */
        $jsToCssFiles = [];
        foreach ($viteManifest as $entry) {
            if (($entry['isEntry'] ?? false) !== true || !isset($entry['name']) || $entry['name'] === '') {
                continue;
            }
            if (isset($entry['css']) && $entry['css'] !== []) {
                $jsToCssFiles[$entry['name']] = $entry['css'];
            }
        }

        $result = [];

        foreach ($viteManifest as $entry) {
            if (($entry['isEntry'] ?? false) !== true || !isset($entry['name']) || $entry['name'] === '' || !isset($entry['file'])) {
                continue;
            }

            $entryName = $entry['name']; // e.g. "CustomApp/Button/Primary", "CustomApp/Button/Primary.scss" (SCSS) or "CustomApp/Button/Primary.css" (plain CSS)
            $outputFile = $entry['file']; // e.g. "CustomApp/Button/Primary.js" or "CustomApp/Button/Primary-hash.css"

            // Style entry keys keep their source extension (.scss or .css) to
            // prevent collisions with same-named JS entries at build time — a
            // component may ship `Primary.js` alongside either `Primary.scss`
            // or `Primary.css`. Strip either suffix here so the runtime tag
            // stays clean (`CustomApp/Button/Primary` → `CustomApp:Button:Primary`).
            $entryName = preg_replace('/\.(scss|css)$/', '', $entryName) ?? $entryName;

            // Derive the component tag: "CustomApp/Button/Primary" → "CustomApp:Button:Primary"
            $tag = str_replace('/', ':', $entryName);

            $publicBase = '/' . self::PUBLIC_COMPONENTS_DIR . '/';

            if (str_ends_with($outputFile, '.css')) {
                $result[$tag]['css'][] = $publicBase . $outputFile;
            } elseif (str_ends_with($outputFile, '.js')) {
                $result[$tag]['js'] = $publicBase . $outputFile;

                if (isset($jsToCssFiles[$entryName])) {
                    foreach ($jsToCssFiles[$entryName] as $cssFile) {
                        $result[$tag]['css'][] = $publicBase . $cssFile;
                    }
                }
            }
        }

        // Dedupe CSS lists while preserving first-occurrence order. This matters
        // when a component has both a sibling .scss (its own canonical entry)
        // and a JS entry whose `css[]` happens to reference the same file.
        foreach ($result as $tag => $entry) {
            if (!isset($entry['css'])) {
                continue;
            }
            $result[$tag]['css'] = array_values(array_unique($entry['css']));
        }

        return $result;
    }

    /**
     * Reads and decodes `var/plugins.json` via the injected LocalFilesystem.
     * Returns null when the file is absent or unreadable.
     *
     * @return array<string, array{basePath?: string}>|null
     */
    private function readPluginsJson(): ?array
    {
        $pluginsJson = $this->projectDir . '/var/plugins.json';

        if (!$this->localFilesystem->exists($pluginsJson)) {
            return null;
        }

        try {
            /** @var array<string, array{basePath?: string}> $plugins */
            $plugins = json_decode(
                $this->localFilesystem->readFile($pluginsJson),
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );
        } catch (IOException|\JsonException) {
            return null;
        }

        return \is_array($plugins) ? $plugins : null;
    }

    /**
     * @param array<string, array{js?: string, css?: list<string>}> $manifest
     */
    private function writeComponentManifest(array $manifest): void
    {
        try {
            $this->tempFilesystem->write(
                self::MANIFEST_PATH,
                json_encode($manifest, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
            );
        } catch (\Throwable) {
            // Non-critical: ThemeCompiler falls back to building an empty import map.
        }
    }
}

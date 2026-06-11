<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Filesystem\Path;

/**
 * Builds cache keys for {@see ScssPhpCompiler::compileString()} invocations.
 *
 * Tightly coupled to the compiler's caching contract; the import-graph walk recognises the
 * three Sass module-loading directives (`@import`, `@use`, `@forward`) so cache keys invalidate
 * when any module the source pulls in changes on disk.
 *
 * @internal
 */
#[Package('framework')]
final class ScssCacheKeyGenerator
{
    private const PREFIX = 'scss_compiler_';

    private const IMPORT_REGEX = '/@(?:import|use|forward)\s+[\'"]([^\'"]+)[\'"]/';

    /**
     * @param FilesystemOperator $filesystem A Flysystem operator rooted at the host filesystem root (`/`); SCSS imports
     *                                       arrive as absolute paths and are passed straight through (Flysystem's
     *                                       path normaliser strips the leading `/` against the root).
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
    ) {
    }

    /**
     * Builds a cache key incorporating the source content, output style, import paths, and
     * the mtimes of all `@import`'d files reachable from the source. The key changes whenever
     * the SCSS dependency tree changes.
     *
     * @param string $outputStyle The scssphp output style as a string (`OutputStyle::COMPRESSED`/`EXPANDED`);
     *                            kept as a string so the key generator works across scssphp versions, where
     *                            `OutputStyle` is a string-constant class (1.x) or a backed enum (2.x).
     * @param array<int|string, mixed> $importPaths
     */
    public function generate(string $scss, ?string $path, string $outputStyle, array $importPaths): string
    {
        $configHash = Hasher::hash([
            'outputStyle' => $outputStyle,
            'importPaths' => $this->serializableImportPaths($importPaths),
        ]);

        $fileFingerprint = '';
        if ($path !== null && $this->fileExists($path)) {
            $fileFingerprint = (string) $this->lastModified($path);
        }

        $stringPaths = array_values(array_filter($importPaths, 'is_string'));
        foreach ($this->findImports($scss, $stringPaths) as $imported) {
            $fileFingerprint .= '_' . $this->lastModified($imported);
        }

        return self::PREFIX . Hasher::hash($scss . $configHash . ($path ?? '') . $fileFingerprint);
    }

    /**
     * Resolves all SCSS files imported (recursively) by $scss against the given import paths.
     * Returns absolute file paths in the order they are first encountered.
     *
     * @param array<int, string> $importPaths
     *
     * @return array<int, string>
     */
    public function findImports(string $scss, array $importPaths): array
    {
        return array_values($this->collect($scss, $importPaths, []));
    }

    /**
     * @param array<int, string> $importPaths
     * @param array<string, true> $visited
     *
     * @return array<string, string>
     */
    private function collect(string $scss, array $importPaths, array $visited): array
    {
        $found = [];
        if (preg_match_all(self::IMPORT_REGEX, $scss, $matches) === false || $matches[1] === []) {
            return $found;
        }

        foreach ($matches[1] as $importPath) {
            $resolved = $this->resolve($importPath, $importPaths);
            if ($resolved === null || isset($visited[$resolved])) {
                continue;
            }
            $visited[$resolved] = true;
            $found[$resolved] = $resolved;
            try {
                $content = $this->filesystem->read($resolved);
            } catch (FilesystemException) {
                continue;
            }
            foreach ($this->collect($content, $importPaths, $visited) as $nested) {
                $found[$nested] = $nested;
                $visited[$nested] = true;
            }
        }

        return $found;
    }

    /**
     * @param array<int, string> $importPaths
     */
    private function resolve(string $importPath, array $importPaths): ?string
    {
        if (!str_ends_with($importPath, '.scss')) {
            $importPath .= '.scss';
        }

        if (Path::isAbsolute($importPath)) {
            return $this->existsOrPartial($importPath);
        }

        foreach ($importPaths as $base) {
            $resolved = $this->existsOrPartial(Path::join($base, $importPath));
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function existsOrPartial(string $candidate): ?string
    {
        if ($this->fileExists($candidate)) {
            return $candidate;
        }
        $partial = Path::join(Path::getDirectory($candidate), '_' . \basename($candidate));
        if ($this->fileExists($partial)) {
            return $partial;
        }

        return null;
    }

    private function fileExists(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path);
        } catch (FilesystemException) {
            return false;
        }
    }

    private function lastModified(string $path): int
    {
        try {
            return $this->filesystem->lastModified($path);
        } catch (FilesystemException) {
            return 0;
        }
    }

    /**
     * Replaces non-serializable entries (closures, objects) with a stable identifier so that
     * the import-path list can participate in a cache key.
     *
     * @param array<int|string, mixed> $importPaths
     *
     * @return array<int|string, mixed>
     */
    private function serializableImportPaths(array $importPaths): array
    {
        $result = [];
        foreach ($importPaths as $key => $value) {
            if ($value === null || \is_scalar($value) || \is_array($value)) {
                $result[$key] = $value;
                continue;
            }

            if ($value instanceof \Closure) {
                $result[$key] = 'Closure_' . spl_object_hash($value);
                continue;
            }

            if (\is_object($value)) {
                $result[$key] = $value::class . '_' . spl_object_hash($value);
                continue;
            }

            // Fall-through for types that json_encode can't represent (resources, etc.).
            // get_debug_type() yields a stable, warning-free label instead of casting to string.
            $result[$key] = get_debug_type($value);
        }

        return $result;
    }
}

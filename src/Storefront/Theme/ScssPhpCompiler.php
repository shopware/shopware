<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal - may be changed in the future
 */
#[Package('framework')]
class ScssPhpCompiler extends AbstractScssCompiler
{
    private Compiler $compiler;

    /**
     * @var array<string, mixed>|null
     */
    private readonly ?array $cacheOptions;

    private ?TagAwareCacheInterface $cache = null;

    /**
     * @param array<string, mixed>|null $cacheOptions
     */
    public function __construct(?array $cacheOptions = null, ?TagAwareCacheInterface $cache = null)
    {
        $this->compiler = new Compiler();
        $this->cacheOptions = $cacheOptions;
        $this->cache = $cache;
    }

    public function reset(): void
    {
        $this->compiler = new Compiler();
    }

    public function compileString(AbstractCompilerConfiguration $config, string $scss, ?string $path = null): string
    {
        if ($config->getValue('skipCache') === true || !$this->cacheOptions || !$this->cache) {
            return $this->doCompileString($config, $scss, $path);
        }

        $cacheKey = $this->generateCacheKey($config, $scss, $path);
        $cachedResult = $this->cache->get($cacheKey, function ($item) use ($config, $scss, $path) {
            if (isset($this->cacheOptions['lifetime'])) {
                $item->expiresAfter($this->cacheOptions['lifetime']);
            }

            if (isset($this->cacheOptions['tags']) && \is_array($this->cacheOptions['tags'])) {
                $item->tag($this->cacheOptions['tags']);
            }

            return $this->doCompileString($config, $scss, $path);
        });

        return $cachedResult;
    }

    private function doCompileString(AbstractCompilerConfiguration $config, string $scss, ?string $path = null): string
    {
        $outputStyle = $config->getValue('outputStyle');

        if ($outputStyle === OutputStyle::COMPRESSED || $outputStyle === OutputStyle::EXPANDED) {
            $this->compiler->setOutputStyle($outputStyle);
        }

        $importPaths = $config->getValue('importPaths');

        if ($importPaths !== null) {
            $this->compiler->setImportPaths($importPaths);
        }

        $css = $this->compiler->compileString($scss, $path)->getCss();

        $this->reset(); // Reset compiler for multiple usage

        return $css;
    }

    private function generateCacheKey(AbstractCompilerConfiguration $config, string $scss, ?string $path): string
    {
        $outputStyle = $config->getValue('outputStyle');
        $importPaths = $config->getValue('importPaths');

        // Create an array of serializable config values
        $serializableConfig = [
            'outputStyle' => $this->makeSerializable($outputStyle),
            'importPaths' => $this->makeSerializable($importPaths),
        ];

        $configHash = Hasher::hash(serialize($serializableConfig));

        // Add timestamp to cache key to invalidate cache on file changes
        $fileTimestamp = '';
        if ($path !== null && file_exists($path)) {
            $fileTimestamp = (string) filemtime($path);

            // Also check for imported files timestamps if import paths are provided
            if (\is_array($importPaths)) {
                $importedFiles = $this->findImportedFiles($scss, $importPaths);
                foreach ($importedFiles as $importFile) {
                    if (file_exists($importFile)) {
                        $fileTimestamp .= '_' . filemtime($importFile);
                    }
                }
            }
        }

        return 'scss_compiler_' . Hasher::hash($scss . $configHash . ($path ?? '') . $fileTimestamp);
    }

    /**
     * Extracts @import statements from SCSS content to find imported files
     *
     * @param string $scss The SCSS content
     * @param array<string|mixed> $importPaths The import paths to search for imports
     *
     * @return array<string> The list of found import file paths
     */
    private function findImportedFiles(string $scss, array $importPaths): array
    {
        $importedFiles = [];
        $matches = [];

        // Extract all @import statements
        if (preg_match_all('/@import\s+[\'"]([^\'"]+)[\'"]\s*;/', $scss, $matches)) {
            foreach ($matches[1] as $importPath) {
                // Add .scss extension if not present
                if (!str_ends_with($importPath, '.scss')) {
                    $importPath .= '.scss';
                }

                // Check each import path for the file
                foreach ($importPaths as $basePath) {
                    $fullPath = \is_string($basePath) ? rtrim($basePath, '/') . '/' . $importPath : null;
                    if ($fullPath && file_exists($fullPath)) {
                        $importedFiles[] = $fullPath;

                        // Also check for nested imports in the imported file
                        $importedContent = file_get_contents($fullPath);
                        if ($importedContent !== false) {
                            $nestedImports = $this->findImportedFiles($importedContent, $importPaths);
                            $importedFiles = array_merge($importedFiles, $nestedImports);
                        }

                        break;
                    }
                }
            }
        }

        return array_unique($importedFiles);
    }

    /**
     * Makes value serializable by handling closures and objects that might not be serializable
     */
    private function makeSerializable(mixed $value): mixed
    {
        if (\is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->makeSerializable($v);
            }

            return $result;
        }

        if ($value instanceof \Closure) {
            return 'Closure_' . spl_object_hash($value);
        }

        if (\is_object($value) && !($value instanceof \Stringable) && !method_exists($value, '__toString')) {
            return $value::class . '_' . spl_object_hash($value);
        }

        return $value;
    }
}

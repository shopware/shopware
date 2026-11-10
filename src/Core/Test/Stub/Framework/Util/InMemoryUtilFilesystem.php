<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * In-memory Symfony {@see Filesystem} double: the write methods a unit test needs, backed by an array, so
 * filesystem-consuming code can be driven without touching disk. Seed existing files through the constructor
 * and read the result back with {@see self::dumpedFiles()}. Paths are canonicalized, so a directory exists once
 * it was created with mkdir() or any file or directory lies below it.
 *
 * @internal
 */
#[Package('framework')]
final class InMemoryUtilFilesystem extends Filesystem
{
    /**
     * @var array<string, string> keyed by canonical path
     */
    private array $files = [];

    /**
     * @var array<string, true> keyed by canonical path
     */
    private array $directories = [];

    /**
     * @param array<string, string> $files keyed by absolute path
     */
    public function __construct(array $files = [])
    {
        foreach ($files as $path => $content) {
            $this->files[Path::canonicalize($path)] = $content;
        }
    }

    /**
     * @param string|iterable<string> $dirs
     */
    public function mkdir(string|iterable $dirs, int $mode = 0o777): void
    {
        foreach (self::canonicalize($dirs) as $dir) {
            $this->directories[$dir] = true;
        }
    }

    /**
     * @param string|iterable<string> $files
     */
    public function touch(string|iterable $files, ?int $time = null, ?int $atime = null): void
    {
        foreach (self::canonicalize($files) as $file) {
            $this->files[$file] ??= '';
        }
    }

    /**
     * @param string|iterable<string> $files
     */
    public function exists(string|iterable $files): bool
    {
        $entries = [...array_keys($this->files), ...array_keys($this->directories)];

        foreach (self::canonicalize($files) as $path) {
            if (array_filter($entries, static fn (string $entry): bool => self::isSameOrBelow($entry, $path)) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string|iterable<string> $files
     */
    public function remove(string|iterable $files): void
    {
        foreach (self::canonicalize($files) as $path) {
            $keep = static fn (string $entry): bool => !self::isSameOrBelow($entry, $path);

            $this->files = array_filter($this->files, $keep, \ARRAY_FILTER_USE_KEY);
            $this->directories = array_filter($this->directories, $keep, \ARRAY_FILTER_USE_KEY);
        }
    }

    public function readFile(string $filename): string
    {
        $path = Path::canonicalize($filename);

        if (!\array_key_exists($path, $this->files)) {
            throw new IOException(\sprintf('Failed to read file "%s": file does not exist.', $filename));
        }

        return $this->files[$path];
    }

    public function dumpFile(string $filename, $content): void
    {
        $this->files[Path::canonicalize($filename)] = (string) $content;
    }

    public function appendToFile(string $filename, $content, bool $lock = false): void
    {
        $path = Path::canonicalize($filename);

        $this->files[$path] = ($this->files[$path] ?? '') . $content;
    }

    /**
     * @return array<string, string>
     */
    public function dumpedFiles(): array
    {
        return $this->files;
    }

    /**
     * @param string|iterable<string> $paths
     *
     * @return list<string>
     */
    private static function canonicalize(string|iterable $paths): array
    {
        $canonical = [];
        foreach (\is_iterable($paths) ? $paths : [$paths] as $path) {
            $canonical[] = Path::canonicalize($path);
        }

        return $canonical;
    }

    private static function isSameOrBelow(string $entry, string $path): bool
    {
        return $entry === $path || str_starts_with($entry, rtrim($path, '/') . '/');
    }
}

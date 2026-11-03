<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[Package('framework')]
class Filesystem
{
    /**
     * Image types a browser renders from a `data:image/png;base64` URL, which is how the Administration shows stored icons
     */
    public const RASTER_IMAGE_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public const MAX_IMAGE_SIZE = 1024 * 1024;

    public function __construct(
        public readonly string $location,
        public readonly Io $io = new Io(),
    ) {
    }

    /**
     * Only paths inside the location exist, also after resolving symlinks.
     * The location itself is resolved as well, so a symlinked location keeps working.
     */
    public function has(string ...$path): bool
    {
        $path = Path::join($this->location, ...$path);

        if (!Path::isBasePath($this->location, $path) || !$this->io->exists($path)) {
            return false;
        }

        $realLocation = realpath($this->location);
        $realPath = realpath($path);

        return $realLocation !== false && $realPath !== false && Path::isBasePath($realLocation, $realPath);
    }

    public function hasFile(string ...$path): bool
    {
        return $this->has(...$path) && is_file($this->path(...$path));
    }

    /**
     * Returns null when the path is not a regular file inside the location, is larger than `$maxSize` bytes,
     * or its content is not one of `$mimeTypes`.
     *
     * @param list<string> $mimeTypes
     */
    public function readImage(string $path, array $mimeTypes = self::RASTER_IMAGE_MIME_TYPES, int $maxSize = self::MAX_IMAGE_SIZE): ?string
    {
        if (!$this->hasFile($path) || $this->fileSize($path) > $maxSize) {
            return null;
        }

        $content = $this->read($path);
        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($content);

        return \in_array($mimeType, $mimeTypes, true) ? $content : null;
    }

    public function fileSize(string ...$path): int
    {
        if (!$this->hasFile(...$path)) {
            throw UtilException::cannotFindFileInFilesystem(Path::join(...$path), $this->location);
        }

        return (int) filesize(Path::join($this->location, ...$path));
    }

    public function path(string ...$path): string
    {
        return Path::join($this->location, ...$path);
    }

    public function realpath(string ...$path): string
    {
        if (!$this->has(...$path)) {
            throw UtilException::cannotFindFileInFilesystem(Path::join(...$path), $this->location);
        }

        return (string) realpath(Path::join($this->location, ...$path));
    }

    public function read(string ...$path): string
    {
        if (!$this->has(...$path)) {
            throw UtilException::cannotFindFileInFilesystem(Path::join(...$path), $this->location);
        }

        return (string) file_get_contents(Path::join($this->location, ...$path));
    }

    /**
     * @param string $name The pattern to search for, eg '*.json'
     * @param string $in The relative directory to search in
     *
     * @return array<SplFileInfo>
     */
    public function findFiles(string $name, string $in): array
    {
        $finder = new Finder();
        $finder->in(Path::join($this->location, $in))
            ->files()
            ->name($name);

        return array_values(iterator_to_array($finder));
    }
}

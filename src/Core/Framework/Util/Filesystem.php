<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Util\FilesystemTest
 */
#[Package('framework')]
class Filesystem
{
    public function __construct(
        public readonly string $location,
        public readonly Io $io = new Io(),
    ) {
    }

    public function has(string ...$path): bool
    {
        $path = $this->path(...$path);

        return \is_dir($path) || \is_file($path);
    }

    public function hasFile(string ...$path): bool
    {
        $path = $this->path(...$path);

        return \is_file($path);
    }

    public function path(string ...$path): string
    {
        $maxPathLength = \PHP_MAXPATHLEN - 2;

        $path = Path::join($this->location, ...$path);

        if (\strlen($path) > $maxPathLength) {
            throw new IOException(\sprintf('Could not check if file exist because path length exceeds %d characters.', $maxPathLength), 0, null, $path);
        }

        return $path;
    }

    public function realpath(string ...$path): string
    {
        if (!$this->has(...$path)) {
            throw UtilException::cannotFindFileInFilesystem(Path::join(...$path), $this->location);
        }

        return (string) realpath($this->path(...$path));
    }

    public function read(string ...$path): string
    {
        if (!$this->has(...$path)) {
            throw UtilException::cannotFindFileInFilesystem(Path::join(...$path), $this->location);
        }

        return (string) file_get_contents($this->path(...$path));
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
        $finder->in($this->path($in))
            ->files()
            ->name($name);

        return array_values(iterator_to_array($finder));
    }
}

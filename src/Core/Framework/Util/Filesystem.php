<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;
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
#[Package('core')]
class Filesystem
{
    public function __construct(
        public readonly string $location,
        public readonly Io $io = new Io(),
    ) {
    }

    public function has(string ...$path): bool
    {
        return $this->io->exists(Path::join($this->location, ...$path));
    }

    public function hasFile(string ...$path): bool
    {
        $path = Path::join($this->location, ...$path);

        return $this->io->exists($path) && !is_dir($path);
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
     * @param string $in   The relative directory to search in
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

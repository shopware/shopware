<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * In-memory Symfony {@see Filesystem} double: the write methods a unit test needs, backed by an array, so
 * filesystem-consuming code can be driven without touching disk. Seed existing files through the constructor
 * and read the result back with {@see self::dumpedFiles()}.
 *
 * @internal
 */
#[Package('framework')]
final class InMemoryUtilFilesystem extends Filesystem
{
    /**
     * @param array<string, string> $files keyed by absolute path
     */
    public function __construct(private array $files = [])
    {
    }

    /**
     * @param string|iterable<string> $files
     */
    public function exists(string|iterable $files): bool
    {
        foreach (\is_iterable($files) ? $files : [$files] as $file) {
            if (!\array_key_exists($file, $this->files)) {
                return false;
            }
        }

        return true;
    }

    public function readFile(string $filename): string
    {
        if (!\array_key_exists($filename, $this->files)) {
            throw new IOException(\sprintf('Failed to read file "%s": file does not exist.', $filename));
        }

        return $this->files[$filename];
    }

    public function dumpFile(string $filename, $content): void
    {
        $this->files[$filename] = (string) $content;
    }

    public function appendToFile(string $filename, $content, bool $lock = false): void
    {
        $this->files[$filename] = ($this->files[$filename] ?? '') . $content;
    }

    /**
     * @return array<string, string>
     */
    public function dumpedFiles(): array
    {
        return $this->files;
    }
}

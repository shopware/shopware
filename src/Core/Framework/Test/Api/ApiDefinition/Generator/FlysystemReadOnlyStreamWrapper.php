<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * Incomplete adapter to make Flysystem usable via stream wrapper.
 *
 * It allows using the test Flysystem instance with file system related code expecting string paths.
 * This class only implements the methods for reading files and directories, writes have to be
 * done using the Flysystem instance directly.
 */
class FlysystemReadOnlyStreamWrapper
{
    use KernelTestBehaviour;
    use FilesystemBehaviour;

    private const MODES = [
        'file' => 0100644, // rw-r--r--
        'dir' => 0040755,  // rwxr-xr-x
    ];

    /**
     * @var string
     */
    private $filePath;

    /**
     * @var int
     */
    private $fileOffset = 0;

    /**
     * @var mixed[]
     */
    private $dirListing = [];

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $filePath = $this->parseWrapperFilename($path);

        if (!$this->isFile($filePath)) {
            return false;
        }

        $this->filePath = $filePath;
        $this->fileOffset = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $buffer = mb_substr($this->getFileContents(), $this->fileOffset, $count);
        $this->fileOffset = min($this->fileOffset + $count, $this->getSize());

        return $buffer;
    }

    public function stream_tell(): int
    {
        return $this->fileOffset;
    }

    public function stream_eof(): bool
    {
        return $this->getSize() === $this->fileOffset;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        $setters = [
            SEEK_SET => 'setAbsoluteOffset',
            SEEK_CUR => 'setRelativeOffset',
            SEEK_END => 'setOffsetFromEnd',
        ];

        return isset($setters[$whence])
            ? $this->{$setters[$whence]}($offset)
            : false;
    }

    /**
     * @return array|bool
     */
    public function stream_stat()
    {
        return $this->url_stat($this->filePath, 0);
    }

    /**
     * @return array|bool
     */
    public function url_stat(string $path, int $flags)
    {
        $filePath = $this->parseWrapperFilename($path);
        $type = $this->getItemType($filePath);
        if (!isset(self::MODES[$type])) {
            return false;
        }
        $size = $this->isFile($filePath) ? $this->getMetadata($filePath)['size'] : 0;
        $timestamp = $this->getMetadata($filePath)['timestamp'] ?? 0;

        return [
            1 => 0,
            'ino' => 0,
            2 => self::MODES[$type],
            'mode' => self::MODES[$type],
            3 => 0,
            'nlink' => 0,
            4 => 0,
            'uid' => 0,
            5 => 0,
            'gid' => 0,
            6 => 0,
            'rdev' => 0,
            7 => $size,
            'size' => $size,
            8 => $timestamp,
            'atime' => $timestamp,
            9 => $timestamp,
            'mtime' => $timestamp,
            10 => $timestamp,
            'ctime' => $timestamp,
            11 => 0,
            'blksize' => 0,
            12 => 0,
            'blocks' => 0,
        ];
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $filename = $this->parseWrapperFilename($path);

        if ($this->isDir($filename)) {
            $this->dirListing = $this->getFilesystemInstance()->listContents($filename);

            return true;
        }

        return false;
    }

    /**
     * @return string|bool
     */
    public function dir_readdir()
    {
        $item = current($this->dirListing);
        next($this->dirListing);

        return $item['basename'] ?? false;
    }

    public function dir_rewinddir(): bool
    {
        reset($this->dirListing);

        return true;
    }

    private function getFilesystemInstance(): Filesystem
    {
        return $this->getFilesystem('shopware.filesystem.private');
    }

    private function parseWrapperFilename(string $path): string
    {
        $parts = parse_url(str_replace(':///', '://', $path));

        return ($parts['host'] ?? '') . ($parts['path'] ?? '');
    }

    private function getMetadata(string $filename): array
    {
        try {
            return $this->getFilesystemInstance()->getMetadata($filename);
        } catch (FileNotFoundException $exception) {
            return [];
        }
    }

    private function getSize(): int
    {
        return $this->getMetadata($this->filePath)['size'];
    }

    private function getFileContents(): string
    {
        return $this->getFilesystemInstance()->read($this->filePath);
    }

    private function setAbsoluteOffset(int $offset): bool
    {
        if ($offset < $this->getSize() && $offset >= 0) {
            $this->fileOffset = $offset;

            return true;
        }

        return false;
    }

    private function setRelativeOffset(int $offset): bool
    {
        return $offset >= 0
            ? $this->setAbsoluteOffset($this->fileOffset + $offset)
            : false;
    }

    private function setOffsetFromEnd(int $offset): bool
    {
        $size = $this->getSize();

        return strlen($size) + $offset >= 0
            ? $this->setAbsoluteOffset(strlen($size) + $offset)
            : false;
    }

    private function getItemType(string $filePath): string
    {
        try {
            $data = $this->getMetadata($filePath);

            return $data['type'] ?? '';
        } catch (FileNotFoundException $exception) {
            return '';
        }
    }

    private function isDir(string $filePath): bool
    {
        return $this->getItemType($filePath) === 'dir';
    }

    private function isFile(string $filePath): bool
    {
        return $this->getItemType($filePath) === 'file';
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFile;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @see https://github.com/thephpleague/flysystem/issues/1477
 */
#[Package('core')]
class MemoryFilesystemAdapter implements FilesystemAdapter
{
    final public const DUMMY_FILE_FOR_FORCED_LISTING_IN_FLYSYSTEM_TEST = '______DUMMY_FILE_FOR_FORCED_LISTING_IN_FLYSYSTEM_TEST';

    /**
     * @var InMemoryFile[]
     */
    private array $files = [];

    private readonly FinfoMimeTypeDetector|MimeTypeDetector $mimeTypeDetector;

    public function __construct(
        private readonly string $defaultVisibility = Visibility::PUBLIC,
        ?MimeTypeDetector $mimeTypeDetector = null
    ) {
        $this->mimeTypeDetector = $mimeTypeDetector ?: new FinfoMimeTypeDetector();
    }

    public function fileExists(string $path): bool
    {
        return \array_key_exists($this->preparePath($path), $this->files);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $path = $this->preparePath($path);
        $file = $this->files[$path] ??= new InMemoryFile();
        $file->updateContents($contents, $config->get('timestamp'));

        $visibility = $config->get(Config::OPTION_VISIBILITY, $this->defaultVisibility);
        $file->setVisibility($visibility);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, (string) stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        $path = $this->preparePath($path);

        if (!\array_key_exists($path, $this->files)) {
            throw UnableToReadFile::fromLocation($path, 'file does not exist');
        }

        return $this->files[$path]->read();
    }

    public function readStream(string $path)
    {
        $path = $this->preparePath($path);

        if (!\array_key_exists($path, $this->files)) {
            throw UnableToReadFile::fromLocation($path, 'file does not exist');
        }

        return $this->files[$path]->readStream();
    }

    public function delete(string $path): void
    {
        unset($this->files[$this->preparePath($path)]);
    }

    public function deleteDirectory(string $prefix): void
    {
        $prefix = $this->preparePath($prefix);
        $prefix = rtrim($prefix, '/') . '/';

        foreach (array_keys($this->files) as $path) {
            if (str_starts_with($path, $prefix)) {
                unset($this->files[$path]);
            }
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $filePath = rtrim($path, '/') . '/' . self::DUMMY_FILE_FOR_FORCED_LISTING_IN_FLYSYSTEM_TEST;
        $this->write($filePath, '', $config);
    }

    public function directoryExists(string $path): bool
    {
        $prefix = $this->preparePath($path);
        $prefix = rtrim($prefix, '/') . '/';

        foreach (array_keys($this->files) as $path) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $path = $this->preparePath($path);

        if (!\array_key_exists($path, $this->files)) {
            throw UnableToSetVisibility::atLocation($path, 'file does not exist');
        }

        $this->files[$path]->setVisibility($visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        $path = $this->preparePath($path);

        if (!\array_key_exists($path, $this->files)) {
            throw UnableToRetrieveMetadata::visibility($path, 'file does not exist');
        }

        return new FileAttributes($path, null, $this->files[$path]->visibility());
    }

    public function mimeType(string $path): FileAttributes
    {
        $preparedPath = $this->preparePath($path);

        if (!\array_key_exists($preparedPath, $this->files)) {
            throw UnableToRetrieveMetadata::mimeType($path, 'file does not exist');
        }

        $mimeType = $this->mimeTypeDetector->detectMimeType($path, $this->files[$preparedPath]->read());

        if ($mimeType === null) {
            throw UnableToRetrieveMetadata::mimeType($path);
        }

        return new FileAttributes($preparedPath, null, null, null, $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        $path = $this->preparePath($path);

        if (!\array_key_exists($path, $this->files)) {
            throw UnableToRetrieveMetadata::lastModified($path, 'file does not exist');
        }

        return new FileAttributes($path, null, null, $this->files[$path]->lastModified());
    }

    public function fileSize(string $path): FileAttributes
    {
        $path = $this->preparePath($path);

        if (!\array_key_exists($path, $this->files)) {
            throw UnableToRetrieveMetadata::fileSize($path, 'file does not exist');
        }

        return new FileAttributes($path, $this->files[$path]->fileSize());
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = rtrim($this->preparePath($path), '/') . '/';
        $prefixLength = \strlen($prefix);
        $listedDirectories = [];

        foreach ($this->files as $path => $file) {
            if (str_starts_with($path, $prefix)) {
                $subPath = substr($path, $prefixLength);
                $dirname = \dirname($subPath);

                if ($dirname !== '.') {
                    $parts = explode('/', $dirname);
                    $dirPath = '';

                    foreach ($parts as $index => $part) {
                        if ($deep === false && $index >= 1) {
                            break;
                        }

                        $dirPath .= $part . '/';

                        if (!\in_array($dirPath, $listedDirectories, true)) {
                            $listedDirectories[] = $dirPath;
                            yield new DirectoryAttributes(trim($prefix . $dirPath, '/'));
                        }
                    }
                }

                $dummyFilename = self::DUMMY_FILE_FOR_FORCED_LISTING_IN_FLYSYSTEM_TEST;
                if (str_ends_with($path, $dummyFilename)) {
                    continue;
                }

                if ($deep === true || !str_contains($subPath, '/')) {
                    yield new FileAttributes(ltrim($path, '/'), $file->fileSize(), $file->visibility(), $file->lastModified(), $file->mimeType());
                }
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $source = $this->preparePath($source);
        $sourceLength = \strlen($source);
        $destination = $this->preparePath($destination);

        if ($this->fileExists($source)) {
            if ($this->fileExists($destination) || $this->directoryExists($destination)) {
                throw UnableToMoveFile::fromLocationTo($source, $destination);
            }

            $this->files[$destination] = $this->files[$source];
            unset($this->files[$source]);

            return;
        }

        if (!$this->directoryExists($source) || $this->directoryExists($destination) || $this->fileExists($destination)) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }

        foreach ($this->files as $path => $file) {
            if (str_starts_with($path, $source)) {
                $newPath = $destination . substr($path, $sourceLength);
                $this->files[$newPath] = $file;
                unset($this->files[$path]);
            }
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $source = $this->preparePath($source);
        $destination = $this->preparePath($destination);

        if (!$this->fileExists($source)) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }

        $lastModified = $config->get('timestamp', time());

        $this->files[$destination] = $this->files[$source]->withLastModified($lastModified);
    }

    public function deleteEverything(): void
    {
        $this->files = [];
    }

    private function preparePath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
}

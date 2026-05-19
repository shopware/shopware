<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\Visibility;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('discovery')]
final readonly class DeleteFileHandler
{
    /**
     * @internal
     */
    public function __construct(
        private FilesystemOperator $filesystemPublic,
        private FilesystemOperator $filesystemPrivate
    ) {
    }

    public function __invoke(DeleteFileMessage $message): void
    {
        $filesystem = $this->getFileSystem($message->getVisibility());

        foreach ($message->getFiles() as $file) {
            try {
                $filesystem->delete($file);
            } catch (UnableToDeleteFile) {
                // ignore file is already deleted
            }
        }

        if (!$message->isDeleteEmptyDirectories()) {
            return;
        }

        foreach ($message->getFiles() as $file) {
            try {
                $this->deleteEmptyDirectories($filesystem, $file);
            } catch (FilesystemException) {
                // ignore already deleted directories
            }
        }
    }

    private function deleteEmptyDirectories(FilesystemOperator $filesystem, string $path): void
    {
        $directory = \dirname($path);

        if (
            $directory === ''
            || \dirname($directory) === '.'
            || \dirname($directory) === '/'
            || !$this->isDirectoryEmpty($filesystem, $directory)
        ) {
            return;
        }

        $filesystem->deleteDirectory($directory);

        $this->deleteEmptyDirectories($filesystem, $directory);
    }

    private function isDirectoryEmpty(FilesystemOperator $filesystem, string $path): bool
    {
        foreach ($filesystem->listContents($path) as $ignored) {
            return false;
        }

        return true;
    }

    private function getFileSystem(string $visibility): FilesystemOperator
    {
        return match ($visibility) {
            Visibility::PUBLIC => $this->filesystemPublic,
            Visibility::PRIVATE => $this->filesystemPrivate,
            default => throw MediaException::invalidFilesystemVisibility(),
        };
    }
}

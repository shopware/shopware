<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\Visibility;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @package content
 *
 * @internal
 */
final class DeleteFileHandler implements MessageHandlerInterface
{
    private FilesystemOperator $filesystemPublic;

    private FilesystemOperator $filesystemPrivate;

    /**
     * @internal
     */
    public function __construct(FilesystemOperator $filesystemPublic, FilesystemOperator $filesystemPrivate)
    {
        $this->filesystemPublic = $filesystemPublic;
        $this->filesystemPrivate = $filesystemPrivate;
    }

    public function __invoke(DeleteFileMessage $message): void
    {
        foreach ($message->getFiles() as $file) {
            try {
                $this->getFileSystem($message->getVisibility())->delete($file);
            } catch (UnableToDeleteFile $e) {
                //ignore file is already deleted
            }
        }
    }

    private function getFileSystem(string $visibility): FilesystemOperator
    {
        switch ($visibility) {
            case Visibility::PUBLIC:
                return $this->filesystemPublic;
            case Visibility::PRIVATE:
                return $this->filesystemPrivate;
            default:
                throw new \RuntimeException('Invalid filesystem visibility.');
        }
    }
}

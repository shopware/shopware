<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\Visibility;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;

/**
 * @package content
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will only implement MessageHandlerInterface and all MessageHandler will be internal and final starting with v6.5.0.0
 */
class DeleteFileHandler extends AbstractMessageHandler
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

    /**
     * @param DeleteFileMessage $message
     */
    public function handle($message): void
    {
        foreach ($message->getFiles() as $file) {
            try {
                $this->getFileSystem($message->getVisibility())->delete($file);
            } catch (UnableToDeleteFile $e) {
                //ignore file is already deleted
            }
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [DeleteFileMessage::class];
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

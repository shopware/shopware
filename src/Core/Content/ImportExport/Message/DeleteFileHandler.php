<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Message;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;

class DeleteFileHandler extends AbstractMessageHandler
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param DeleteFileMessage $message
     */
    public function handle($message): void
    {
        foreach ($message->getFiles() as $file) {
            try {
                $this->filesystem->delete($file);
            } catch (FileNotFoundException $e) {
                //ignore file is already deleted
            }
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [DeleteFileMessage::class];
    }
}

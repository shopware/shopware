<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Message;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will only implement MessageHandlerInterface and all MessageHandler will be internal and final starting with v6.5.0.0
 */
class DeleteFileHandler extends AbstractMessageHandler
{
    /**
     * @var FilesystemOperator
     */
    private $filesystem;

    /**
     * @internal
     */
    public function __construct(FilesystemOperator $filesystem)
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
            } catch (UnableToDeleteFile $e) {
                //ignore file is already deleted
            }
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [DeleteFileMessage::class];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Message;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * @internal
 *
 * @package system-settings
 */
final class DeleteFileHandler implements MessageHandlerInterface
{
    private FilesystemOperator $filesystem;

    /**
     * @internal
     */
    public function __construct(FilesystemOperator $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function __invoke(DeleteFileMessage $message): void
    {
        foreach ($message->getFiles() as $file) {
            try {
                $this->filesystem->delete($file);
            } catch (UnableToDeleteFile $e) {
                //ignore file is already deleted
            }
        }
    }
}

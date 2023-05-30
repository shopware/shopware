<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Message;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('system-settings')]
final class DeleteFileHandler
{
    /**
     * @internal
     */
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function __invoke(DeleteFileMessage $message): void
    {
        foreach ($message->getFiles() as $file) {
            try {
                $this->filesystem->delete($file);
            } catch (UnableToDeleteFile) {
                //ignore file is already deleted
            }
        }
    }
}

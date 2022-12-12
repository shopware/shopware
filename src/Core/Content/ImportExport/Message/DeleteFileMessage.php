<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @package system-settings
 */
class DeleteFileMessage implements AsyncMessageInterface
{
    /**
     * @var array
     */
    private $files = [];

    public function getFiles(): array
    {
        return $this->files;
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }
}

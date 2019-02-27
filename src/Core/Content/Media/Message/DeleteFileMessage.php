<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

class DeleteFileMessage
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

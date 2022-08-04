<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use League\Flysystem\AdapterInterface;

class DeleteFileMessage
{
    private array $files;

    private string $visibility;

    public function __construct(array $files = [], string $visibility = AdapterInterface::VISIBILITY_PUBLIC)
    {
        $this->files = $files;
        $this->visibility = $visibility;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): void
    {
        $this->visibility = $visibility;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use League\Flysystem\Visibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('discovery')]
class DeleteFileMessage implements AsyncMessageInterface
{
    /**
     * @param list<string> $files
     */
    public function __construct(
        private array $files = [],
        private string $visibility = Visibility::PUBLIC,
        private bool $deleteEmptyDirectories = false,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @param list<string> $files
     */
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

    public function isDeleteEmptyDirectories(): bool
    {
        return $this->deleteEmptyDirectories;
    }

    public function setDeleteEmptyDirectories(bool $deleteEmptyDirectories): void
    {
        $this->deleteEmptyDirectories = $deleteEmptyDirectories;
    }
}

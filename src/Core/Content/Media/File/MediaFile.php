<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

/**
 * @package content
 */
class MediaFile
{
    private string $fileName;

    private string $mimeType;

    private string $fileExtension;

    private int $fileSize;

    private ?string $hash;

    public function __construct(
        string $fileName,
        string $mimeType,
        string $fileExtension,
        int $fileSize,
        ?string $hash = null
    ) {
        $this->fileName = $fileName;
        $this->mimeType = $mimeType;
        $this->fileExtension = $fileExtension;
        $this->fileSize = $fileSize;
        $this->hash = $hash;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }
}

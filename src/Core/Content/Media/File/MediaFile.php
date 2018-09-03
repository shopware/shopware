<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

class MediaFile
{
    /**
     * @var string
     */
    private $fileName;

    /**
     * @var string
     */
    private $mimeType;

    /**
     * @var string
     */
    private $fileExtension;

    /**
     * @var int
     */
    private $fileSize;

    public function __construct(
        string $fileName,
        string $mimeType,
        string $fileExtension,
        int $fileSize
    ) {
        $this->fileName = $fileName;
        $this->mimeType = $mimeType;
        $this->fileExtension = $fileExtension;
        $this->fileSize = $fileSize;
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
}

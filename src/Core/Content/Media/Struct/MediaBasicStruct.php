<?php declare(strict_types=1);

namespace Shopware\Content\Media\Struct;

use Shopware\Framework\ORM\Entity;

class MediaBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $albumId;

    /**
     * @var string|null
     */
    protected $userId;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var string
     */
    protected $mimeType;

    /**
     * @var int
     */
    protected $fileSize;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $metaData;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var MediaAlbumBasicStruct
     */
    protected $album;

    public function getAlbumId(): string
    {
        return $this->albumId;
    }

    public function setAlbumId(string $albumId): void
    {
        $this->albumId = $albumId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getMetaData(): ?string
    {
        return $this->metaData;
    }

    public function setMetaData(?string $metaData): void
    {
        $this->metaData = $metaData;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getAlbum(): MediaAlbumBasicStruct
    {
        return $this->album;
    }

    public function setAlbum(MediaAlbumBasicStruct $album): void
    {
        $this->album = $album;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MediaDefaultFolderEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string[]
     */
    protected $associationFields;

    /**
     * @var array
     */
    protected $thumbnailSizes;

    /**
     * @var string
     */
    protected $entity;

    /**
     * @var MediaFolderEntity|null
     */
    protected $folder;

    /**
     * @var array|null
     */
    protected $attributes;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getAssociationFields(): array
    {
        return $this->associationFields;
    }

    public function setAssociationFields(array $associationFields): void
    {
        $this->associationFields = $associationFields;
    }

    public function getThumbnailSizes(): array
    {
        return $this->thumbnailSizes;
    }

    public function setThumbnailSizes(array $thumbnailSizes): void
    {
        $this->thumbnailSizes = $thumbnailSizes;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getFolder(): ?MediaFolderEntity
    {
        return $this->folder;
    }

    public function setFolder(?MediaFolderEntity $folder): void
    {
        $this->folder = $folder;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
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

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}

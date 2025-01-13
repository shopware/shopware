<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentMedia;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DocumentMediaEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $documentId;

    protected string $mediaId;

    protected string $fileExtension;

    protected ?MediaEntity $media = null;

    protected ?DocumentEntity $document = null;

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function setDocumentId(string $documentId): void
    {
        $this->documentId = $documentId;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function setFileExtension(string $fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getDocument(): ?DocumentEntity
    {
        return $this->document;
    }

    public function setDocument(DocumentEntity $entity): void
    {
        $this->document = $entity;
    }
}

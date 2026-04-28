<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile;

use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentFileEntity extends Entity
{
    use EntityIdTrait;

    protected string $documentId;

    protected string $mediaId;

    protected string $documentFormat;

    protected ?DocumentEntity $document = null;

    protected MediaEntity $media;

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

    public function getDocumentFormat(): string
    {
        return $this->documentFormat;
    }

    public function setDocumentFormat(string $documentFormat): void
    {
        $this->documentFormat = $documentFormat;
    }

    public function getDocument(): ?DocumentEntity
    {
        return $this->document;
    }

    public function setDocument(DocumentEntity $document): void
    {
        $this->document = $document;
    }

    public function getMedia(): MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }
}

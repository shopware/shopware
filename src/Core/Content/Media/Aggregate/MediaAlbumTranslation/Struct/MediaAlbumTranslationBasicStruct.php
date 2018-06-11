<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Struct;

use Shopware\Core\Framework\ORM\Entity;

class MediaAlbumTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $mediaAlbumId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    public function getMediaAlbumId(): string
    {
        return $this->mediaAlbumId;
    }

    public function setMediaAlbumId(string $mediaAlbumId): void
    {
        $this->mediaAlbumId = $mediaAlbumId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

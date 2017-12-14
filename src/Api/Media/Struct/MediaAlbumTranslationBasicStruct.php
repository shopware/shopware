<?php declare(strict_types=1);

namespace Shopware\Api\Media\Struct;

use Shopware\Api\Entity\Entity;

class MediaAlbumTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $mediaAlbumUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    public function getMediaAlbumUuid(): string
    {
        return $this->mediaAlbumUuid;
    }

    public function setMediaAlbumUuid(string $mediaAlbumUuid): void
    {
        $this->mediaAlbumUuid = $mediaAlbumUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
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

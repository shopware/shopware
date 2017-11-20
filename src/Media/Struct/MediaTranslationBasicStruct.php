<?php declare(strict_types=1);

namespace Shopware\Media\Struct;

use Shopware\Api\Entity\Entity;

class MediaTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $mediaUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    public function getMediaUuid(): string
    {
        return $this->mediaUuid;
    }

    public function setMediaUuid(string $mediaUuid): void
    {
        $this->mediaUuid = $mediaUuid;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}

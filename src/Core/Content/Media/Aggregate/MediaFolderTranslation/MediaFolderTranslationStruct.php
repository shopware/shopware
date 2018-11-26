<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderTranslation;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class MediaFolderTranslationStruct extends Entity
{
    /**
     * @var MediaFolderStruct
     */
    protected $mediaFolder;

    /**
     * @var LanguageStruct
     */
    protected $language;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

    /**
     * @var string
     */
    protected $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getMediaFolder(): MediaFolderStruct
    {
        return $this->mediaFolder;
    }

    public function setMediaFolder(MediaFolderStruct $mediaFolder): void
    {
        $this->mediaFolder = $mediaFolder;
    }

    public function getLanguage(): LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}

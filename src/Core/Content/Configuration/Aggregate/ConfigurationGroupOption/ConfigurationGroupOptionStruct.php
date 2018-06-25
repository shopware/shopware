<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationCollection;
use Shopware\Core\Content\Configuration\ConfigurationGroupStruct;
use Shopware\Core\Framework\ORM\Entity;

class ConfigurationGroupOptionStruct extends Entity
{
    /**
     * @var string
     */
    protected $groupId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $colorHexCode;

    /**
     * @var string|null
     */
    protected $mediaId;

    /**
     * @var ConfigurationGroupStruct
     */
    protected $group;

    /**
     * @var ConfigurationGroupOptionTranslationCollection|null
     */
    protected $translations;

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getColorHexCode(): ?string
    {
        return $this->colorHexCode;
    }

    public function setColorHexCode(?string $colorHexCode): void
    {
        $this->colorHexCode = $colorHexCode;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getGroup(): ConfigurationGroupStruct
    {
        return $this->group;
    }

    public function setGroup(ConfigurationGroupStruct $group): void
    {
        $this->group = $group;
    }

    public function getTranslations(): ?ConfigurationGroupOptionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ConfigurationGroupOptionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation;

use Shopware\Core\Content\Configuration\ConfigurationGroupStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class ConfigurationGroupTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $configurationGroupId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var ConfigurationGroupStruct|null
     */
    protected $configurationGroup;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

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

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getConfigurationGroupId(): string
    {
        return $this->configurationGroupId;
    }

    public function setConfigurationGroupId(string $configurationGroupId): void
    {
        $this->configurationGroupId = $configurationGroupId;
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

    public function getConfigurationGroup(): ?ConfigurationGroupStruct
    {
        return $this->configurationGroup;
    }

    public function setConfigurationGroup(ConfigurationGroupStruct $configurationGroup): void
    {
        $this->configurationGroup = $configurationGroup;
    }

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }
}

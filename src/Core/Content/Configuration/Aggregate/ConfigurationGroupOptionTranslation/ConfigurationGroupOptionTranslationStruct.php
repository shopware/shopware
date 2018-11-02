<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class ConfigurationGroupOptionTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $configurationGroupOptionId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var ConfigurationGroupOptionStruct|null
     */
    protected $configurationGroupOption;

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

    public function getConfigurationGroupOptionId(): string
    {
        return $this->configurationGroupOptionId;
    }

    public function setConfigurationGroupOptionId(string $configurationGroupOptionId): void
    {
        $this->configurationGroupOptionId = $configurationGroupOptionId;
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

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }

    public function getConfigurationGroupOption(): ?ConfigurationGroupOptionStruct
    {
        return $this->configurationGroupOption;
    }

    public function setConfigurationGroupOption(ConfigurationGroupOptionStruct $configurationGroupOption): void
    {
        $this->configurationGroupOption = $configurationGroupOption;
    }
}

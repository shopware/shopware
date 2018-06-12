<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation;

use Shopware\Core\Framework\ORM\Entity;

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
}

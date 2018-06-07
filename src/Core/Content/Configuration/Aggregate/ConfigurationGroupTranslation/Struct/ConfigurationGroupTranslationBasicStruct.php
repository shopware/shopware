<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Struct;

use Shopware\Core\Framework\ORM\Entity;

class ConfigurationGroupTranslationBasicStruct extends Entity
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
}

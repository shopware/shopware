<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation;

use Shopware\Core\Content\Configuration\ConfigurationGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class ConfigurationGroupTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $configurationGroupId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var ConfigurationGroupEntity|null
     */
    protected $configurationGroup;

    public function getConfigurationGroupId(): string
    {
        return $this->configurationGroupId;
    }

    public function setConfigurationGroupId(string $configurationGroupId): void
    {
        $this->configurationGroupId = $configurationGroupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getConfigurationGroup(): ?ConfigurationGroupEntity
    {
        return $this->configurationGroup;
    }

    public function setConfigurationGroup(ConfigurationGroupEntity $configurationGroup): void
    {
        $this->configurationGroup = $configurationGroup;
    }
}

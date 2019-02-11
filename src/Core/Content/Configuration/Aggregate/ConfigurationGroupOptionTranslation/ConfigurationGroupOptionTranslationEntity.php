<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class ConfigurationGroupOptionTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $configurationGroupOptionId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var ConfigurationGroupOptionEntity|null
     */
    protected $configurationGroupOption;

    /**
     * @var array|null
     */
    protected $attributes;

    public function getConfigurationGroupOptionId(): string
    {
        return $this->configurationGroupOptionId;
    }

    public function setConfigurationGroupOptionId(string $configurationGroupOptionId): void
    {
        $this->configurationGroupOptionId = $configurationGroupOptionId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getConfigurationGroupOption(): ?ConfigurationGroupOptionEntity
    {
        return $this->configurationGroupOption;
    }

    public function setConfigurationGroupOption(ConfigurationGroupOptionEntity $configurationGroupOption): void
    {
        $this->configurationGroupOption = $configurationGroupOption;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}

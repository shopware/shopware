<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\ConfigurationGroupTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ConfigurationGroupStruct extends Entity
{
    use EntityIdTrait;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var bool
     */
    protected $filterable;

    /**
     * @var bool
     */
    protected $comparable;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var ConfigurationGroupOptionCollection|null
     */
    protected $options;

    /**
     * @var ConfigurationGroupTranslationCollection|null
     */
    protected $translations;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getFilterable(): bool
    {
        return $this->filterable;
    }

    public function setFilterable(bool $filterable): void
    {
        $this->filterable = $filterable;
    }

    public function getComparable(): bool
    {
        return $this->comparable;
    }

    public function setComparable(bool $comparable): void
    {
        $this->comparable = $comparable;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getOptions(): ?ConfigurationGroupOptionCollection
    {
        return $this->options;
    }

    public function setOptions(ConfigurationGroupOptionCollection $options): void
    {
        $this->options = $options;
    }

    public function getTranslations(): ?ConfigurationGroupTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ConfigurationGroupTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}

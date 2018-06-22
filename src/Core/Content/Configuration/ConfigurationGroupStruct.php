<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;

class ConfigurationGroupStruct extends Entity
{
    /**
     * @var string
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
     * @var EntitySearchResult|null
     */
    protected $options;

    /**
     * @var EntitySearchResult|null
     */
    protected $translations;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
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

    public function getOptions(): ?EntitySearchResult
    {
        return $this->options;
    }

    public function setOptions(EntitySearchResult $options): void
    {
        $this->options = $options;
    }

    public function getTranslations(): ?EntitySearchResult
    {
        return $this->translations;
    }

    public function setTranslations(EntitySearchResult $translations): void
    {
        $this->translations = $translations;
    }
}

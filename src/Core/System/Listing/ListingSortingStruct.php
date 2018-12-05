<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\ListingSortingTranslationCollection;

class ListingSortingStruct extends Entity
{
    use EntityIdTrait;

    /**
     * @var FieldSorting[]
     */
    protected $payload;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string
     */
    protected $uniqueKey;

    /**
     * @var bool
     */
    protected $displayInCategories;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var ListingSortingTranslationCollection|null
     */
    protected $translations;

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getDisplayInCategories(): bool
    {
        return $this->displayInCategories;
    }

    public function setDisplayInCategories(bool $displayInCategories): void
    {
        $this->displayInCategories = $displayInCategories;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCreatedAt(): ?\DateTime
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

    public function getUniqueKey(): string
    {
        return $this->uniqueKey;
    }

    public function setUniqueKey(string $uniqueKey): void
    {
        $this->uniqueKey = $uniqueKey;
    }

    public function getTranslations(): ?ListingSortingTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ListingSortingTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}

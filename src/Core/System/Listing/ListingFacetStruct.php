<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationCollection;

class ListingFacetStruct extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $uniqueKey;

    /**
     * @var string
     */
    protected $payload;

    /**
     * @var ?string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var bool
     */
    protected $displayInCategories;

    /**
     * @var bool
     */
    protected $deletable;

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
     * @var ListingFacetTranslationCollection|null
     */
    protected $translations;

    public function getUniqueKey(): string
    {
        return $this->uniqueKey;
    }

    public function setUniqueKey(string $uniqueKey): void
    {
        $this->uniqueKey = $uniqueKey;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): void
    {
        $this->payload = $payload;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
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

    public function getDeletable(): bool
    {
        return $this->deletable;
    }

    public function setDeletable(bool $deletable): void
    {
        $this->deletable = $deletable;
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

    public function getTranslations(): ?ListingFacetTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ListingFacetTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}

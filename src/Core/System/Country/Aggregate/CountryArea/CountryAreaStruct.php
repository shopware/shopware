<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryArea;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;

class CountryAreaStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var EntitySearchResult|null
     */
    protected $countries;

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

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCountries(): ?EntitySearchResult
    {
        return $this->countries;
    }

    public function setCountries(EntitySearchResult $countries): void
    {
        $this->countries = $countries;
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

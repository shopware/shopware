<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Aggregate\CatalogTranslation;

use Shopware\Core\Content\Catalog\CatalogEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class CatalogTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $catalogId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var CatalogEntity|null
     */
    protected $catalog;

    /**
     * @var array|null
     */
    protected $attributes;

    public function getCatalogId(): string
    {
        return $this->catalogId;
    }

    public function setCatalogId(string $catalogId): void
    {
        $this->catalogId = $catalogId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCatalog(): ?CatalogEntity
    {
        return $this->catalog;
    }

    public function setCatalog(CatalogEntity $catalog): void
    {
        $this->catalog = $catalog;
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

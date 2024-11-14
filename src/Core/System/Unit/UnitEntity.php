<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationCollection;

#[Package('inventory')]
class UnitEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $shortCode;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var UnitTranslationCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $translations;

    /**
     * @var ProductCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $products;

    public function getShortCode(): ?string
    {
        return $this->shortCode;
    }

    public function setShortCode(?string $shortCode): void
    {
        $this->shortCode = $shortCode;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTranslations(): ?UnitTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(UnitTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation;

use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductFeatureSetTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $productFeatureSetId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var ProductFeatureSetEntity
     */
    protected $productFeatureSet;

    public function getProductFeatureSetId(): string
    {
        return $this->productFeatureSetId;
    }

    public function setProductFeatureSetId(string $productFeatureSetId): void
    {
        $this->productFeatureSetId = $productFeatureSetId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getProductFeatureSet(): ProductFeatureSetEntity
    {
        return $this->productFeatureSet;
    }

    public function setProductFeatureSet(ProductFeatureSetEntity $productFeatureSet): void
    {
        $this->productFeatureSet = $productFeatureSet;
    }
}

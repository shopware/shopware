<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSellingTranslation;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductCrossSellingTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $productCrossSellingId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var ProductCrossSellingEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $productCrossSelling;

    public function getProductCrossSellingId(): string
    {
        return $this->productCrossSellingId;
    }

    public function setProductCrossSellingId(string $productCrossSellingId): void
    {
        $this->productCrossSellingId = $productCrossSellingId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getProductCrossSelling(): ?ProductCrossSellingEntity
    {
        return $this->productCrossSelling;
    }

    public function setProductCrossSelling(ProductCrossSellingEntity $productCrossSelling): void
    {
        $this->productCrossSelling = $productCrossSelling;
    }
}

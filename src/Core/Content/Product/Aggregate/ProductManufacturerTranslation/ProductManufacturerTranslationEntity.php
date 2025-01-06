<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductManufacturerTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $productManufacturerId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $productManufacturerVersionId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $description;

    /**
     * @var ProductManufacturerEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $productManufacturer;

    public function getProductManufacturerId(): string
    {
        return $this->productManufacturerId;
    }

    public function setProductManufacturerId(string $productManufacturerId): void
    {
        $this->productManufacturerId = $productManufacturerId;
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

    public function getProductManufacturer(): ?ProductManufacturerEntity
    {
        return $this->productManufacturer;
    }

    public function setProductManufacturer(ProductManufacturerEntity $productManufacturer): void
    {
        $this->productManufacturer = $productManufacturer;
    }

    public function getProductManufacturerVersionId(): string
    {
        return $this->productManufacturerVersionId;
    }

    public function setProductManufacturerVersionId(string $productManufacturerVersionId): void
    {
        $this->productManufacturerVersionId = $productManufacturerVersionId;
    }
}

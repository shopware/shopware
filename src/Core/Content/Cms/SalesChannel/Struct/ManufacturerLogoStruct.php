<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;

class ManufacturerLogoStruct extends ImageStruct
{
    /**
     * @var ProductManufacturerEntity|null
     */
    private $manufacturer;

    public function getManufacturer(): ?ProductManufacturerEntity
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?ProductManufacturerEntity $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getApiAlias(): string
    {
        return 'cms_manufacturer_logo';
    }
}

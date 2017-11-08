<?php declare(strict_types=1);

namespace Shopware\ProductVariant\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Product\Writer\Resource\ProductWriteResource;
use Shopware\ProductManufacturer\Writer\Resource\ProductManufacturerWriteResource;
use Shopware\Tax\Writer\Resource\TaxWriteResource;

class ProductVariantWriteResource extends ProductWriteResource
{
    const CONTAINER_UUID_FIELD = 'container_uuid';

    public function __construct()
    {
        parent::__construct();
        $this->fields['taxUuid'] = new FkField('tax_uuid', TaxWriteResource::class, 'uuid');
        $this->fields['manufacturerUuid'] = new FkField('product_manufacturer_uuid', ProductManufacturerWriteResource::class, 'uuid');
        $this->fields['translations'] = new SubresourceField(ProductTranslationWriteResource::class, 'languageUuid');
        $this->fields[self::CONTAINER_UUID_FIELD] = (new StringField('container_uuid'))->setFlags(new Required());
    }
}

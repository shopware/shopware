<?php declare(strict_types=1);

namespace Shopware\Category\Gateway\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;

class ApiCategoryAttributeResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('category_attribute');
        
        $this->primaryKeyFields['uuid'] = (new StringField('uuid'))->setFlags(new Required());
        $this->fields['categoryUuid'] = (new StringField('category_uuid'))->setFlags(new Required());
        $this->fields['attribute1'] = new StringField('attribute1');
        $this->fields['attribute2'] = new StringField('attribute2');
        $this->fields['attribute3'] = new StringField('attribute3');
        $this->fields['attribute4'] = new StringField('attribute4');
        $this->fields['attribute5'] = new StringField('attribute5');
        $this->fields['attribute6'] = new StringField('attribute6');
        
        
//        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
//        $this->fields['name'] = new TranslatedField('name', ApiResourceShop::class, 'uuid');
//        $this->fields['description'] = new TranslatedField('description', ApiResourceShop::class, 'uuid');
//        $this->fields['descriptionLong'] = new TranslatedField('descriptionLong', ApiResourceShop::class, 'uuid');
//        $this->fields['productManufacturer'] = new ReferenceField('productManufacturerUuid', 'uuid', ApiResourceProductManufacturer::class);
//        $this->fields['productManufacturerUuid'] = new FkField('product_manufacturer_uuid', ApiResourceProductManufacturer::class, 'uuid');
//        $this->fields['taxUuid'] = new FKField('tax_uuid', ApiResourceTax::class, 'uuid');
//        $this->fields['details'] = new SubresourceField(ApiResourceProductDetail::class);
//        $this->fields['translations'] = (new SubresourceField(ApiResourceProductTranslation::class, 'languageUuid'))->setFlags(new Required());
    }
}

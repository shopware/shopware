<?php declare(strict_types=1);

namespace Shopware\Category\Gateway\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;

class ApiCategoryAvoidCustomerGroupResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('category_avoid_customer_group');
        
        $this->fields['categoryId'] = new IntField('category_id');
        $this->fields['categoryUuid'] = (new StringField('category_uuid'))->setFlags(new Required());
        $this->fields['customerGroupId'] = new IntField('customer_group_id');
        $this->fields['customerGroupUuid'] = (new StringField('customer_group_uuid'))->setFlags(new Required());
        
        
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

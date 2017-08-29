<?php declare(strict_types=1);

namespace Shopware\Category\Gateway\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;

class ApiCategoryResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('category');
        
        $this->primaryKeyFields['uuid'] = (new StringField('uuid'))->setFlags(new Required());
        $this->fields['parent'] = new IntField('parent');
        $this->fields['path'] = new StringField('path');
        $this->fields['description'] = (new StringField('description'))->setFlags(new Required());
        $this->fields['position'] = new IntField('position');
        $this->fields['level'] = (new IntField('level'))->setFlags(new Required());
        $this->fields['added'] = (new DateField('added'))->setFlags(new Required());
        $this->fields['changedAt'] = (new DateField('changed_at'))->setFlags(new Required());
        $this->fields['metaKeywords'] = new LongTextField('meta_keywords');
        $this->fields['metaTitle'] = new StringField('meta_title');
        $this->fields['metaDescription'] = new LongTextField('meta_description');
        $this->fields['cmsHeadline'] = new StringField('cms_headline');
        $this->fields['cmsDescription'] = new LongTextField('cms_description');
        $this->fields['template'] = new StringField('template');
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['blog'] = (new IntField('blog'))->setFlags(new Required());
        $this->fields['external'] = new StringField('external');
        $this->fields['hideFilter'] = (new IntField('hide_filter'))->setFlags(new Required());
        $this->fields['hideTop'] = (new IntField('hide_top'))->setFlags(new Required());
        $this->fields['mediaId'] = new IntField('media_id');
        $this->fields['mediaUuid'] = (new StringField('media_uuid'))->setFlags(new Required());
        $this->fields['productBoxLayout'] = new StringField('product_box_layout');
        $this->fields['hideSortings'] = new IntField('hide_sortings');
        $this->fields['sortingIds'] = new LongTextField('sorting_ids');
        $this->fields['facetIds'] = new LongTextField('facet_ids');
        
        
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

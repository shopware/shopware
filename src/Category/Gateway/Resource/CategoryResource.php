<?php declare(strict_types=1);

namespace Shopware\Category\Gateway\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class CategoryResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('category');
        
        $this->primaryKeyFields['uuid'] = (new UuidField('uuid'))->setFlags(new Required());
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
        $this->fields['attributes'] = new SubresourceField(\Shopware\Category\Gateway\Resource\CategoryAttributeResource::class);
        $this->fields['productCategorys'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategoryResource::class);
        $this->fields['productCategoryRos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategoryRoResource::class);
        $this->fields['productCategorySeos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Category\Gateway\Resource\CategoryResource::class,
            \Shopware\Category\Gateway\Resource\CategoryAttributeResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategoryResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategoryRoResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class
        ];
    }
}

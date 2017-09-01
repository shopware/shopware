<?php declare(strict_types=1);

namespace Shopware\Category\Gateway\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class CategoryResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const PATH_FIELD = 'path';
    protected const NAME_FIELD = 'name';
    protected const POSITION_FIELD = 'position';
    protected const LEVEL_FIELD = 'level';
    protected const ADDED_FIELD = 'added';
    protected const CHANGED_AT_FIELD = 'changedAt';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';
    protected const META_TITLE_FIELD = 'metaTitle';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';
    protected const CMS_HEADLINE_FIELD = 'cmsHeadline';
    protected const CMS_DESCRIPTION_FIELD = 'cmsDescription';
    protected const TEMPLATE_FIELD = 'template';
    protected const ACTIVE_FIELD = 'active';
    protected const IS_BLOG_FIELD = 'isBlog';
    protected const EXTERNAL_FIELD = 'external';
    protected const HIDE_FILTER_FIELD = 'hideFilter';
    protected const HIDE_TOP_FIELD = 'hideTop';
    protected const PRODUCT_BOX_LAYOUT_FIELD = 'productBoxLayout';
    protected const PRODUCT_STREAM_UUID_FIELD = 'productStreamUuid';
    protected const HIDE_SORTINGS_FIELD = 'hideSortings';
    protected const SORTING_IDS_FIELD = 'sortingIds';
    protected const FACET_IDS_FIELD = 'facetIds';

    public function __construct()
    {
        parent::__construct('category');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PATH_FIELD] = new LongTextField('path');
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::LEVEL_FIELD] = (new IntField('level'))->setFlags(new Required());
        $this->fields[self::ADDED_FIELD] = (new DateField('added'))->setFlags(new Required());
        $this->fields[self::CHANGED_AT_FIELD] = (new DateField('changed_at'))->setFlags(new Required());
        $this->fields[self::META_KEYWORDS_FIELD] = new LongTextField('meta_keywords');
        $this->fields[self::META_TITLE_FIELD] = new StringField('meta_title');
        $this->fields[self::META_DESCRIPTION_FIELD] = new LongTextField('meta_description');
        $this->fields[self::CMS_HEADLINE_FIELD] = new StringField('cms_headline');
        $this->fields[self::CMS_DESCRIPTION_FIELD] = new LongTextField('cms_description');
        $this->fields[self::TEMPLATE_FIELD] = new StringField('template');
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::IS_BLOG_FIELD] = (new BoolField('is_blog'))->setFlags(new Required());
        $this->fields[self::EXTERNAL_FIELD] = new StringField('external');
        $this->fields[self::HIDE_FILTER_FIELD] = (new BoolField('hide_filter'))->setFlags(new Required());
        $this->fields[self::HIDE_TOP_FIELD] = (new BoolField('hide_top'))->setFlags(new Required());
        $this->fields[self::PRODUCT_BOX_LAYOUT_FIELD] = new StringField('product_box_layout');
        $this->fields[self::PRODUCT_STREAM_UUID_FIELD] = new StringField('product_stream_uuid');
        $this->fields[self::HIDE_SORTINGS_FIELD] = new BoolField('hide_sortings');
        $this->fields[self::SORTING_IDS_FIELD] = new LongTextField('sorting_ids');
        $this->fields[self::FACET_IDS_FIELD] = new LongTextField('facet_ids');
        $this->fields['blogs'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogResource::class);
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', \Shopware\Category\Gateway\Resource\CategoryResource::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', \Shopware\Category\Gateway\Resource\CategoryResource::class, 'uuid'));
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', \Shopware\Media\Gateway\Resource\MediaResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', \Shopware\Media\Gateway\Resource\MediaResource::class, 'uuid'));
        $this->fields['s'] = new SubresourceField(\Shopware\Category\Gateway\Resource\CategoryResource::class);
        $this->fields['avoidCustomerGroups'] = new SubresourceField(\Shopware\Category\Gateway\Resource\CategoryAvoidCustomerGroupResource::class);
        $this->fields['productCategorys'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategoryResource::class);
        $this->fields['productCategorySeos'] = new SubresourceField(\Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class);
        $this->fields['shippingMethodCategorys'] = new SubresourceField(\Shopware\ShippingMethod\Gateway\Resource\ShippingMethodCategoryResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Gateway\Resource\ShopResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogResource::class,
            \Shopware\Category\Gateway\Resource\CategoryResource::class,
            \Shopware\Media\Gateway\Resource\MediaResource::class,
            \Shopware\Category\Gateway\Resource\CategoryAvoidCustomerGroupResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategoryResource::class,
            \Shopware\Product\Gateway\Resource\ProductCategorySeoResource::class,
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodCategoryResource::class,
            \Shopware\Shop\Gateway\Resource\ShopResource::class
        ];
    }
}

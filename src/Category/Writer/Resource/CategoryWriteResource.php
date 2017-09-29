<?php declare(strict_types=1);

namespace Shopware\Category\Writer\Resource;

use Shopware\Category\Event\CategoryWrittenEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource\BlogWriteResource;
use Shopware\Framework\Write\WriteResource;
use Shopware\Media\Writer\Resource\MediaWriteResource;
use Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource;
use Shopware\Product\Writer\Resource\ProductCategoryWriteResource;
use Shopware\ShippingMethod\Writer\Resource\ShippingMethodCategoryWriteResource;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class CategoryWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const PATH_FIELD = 'path';
    protected const POSITION_FIELD = 'position';
    protected const LEVEL_FIELD = 'level';
    protected const TEMPLATE_FIELD = 'template';
    protected const ACTIVE_FIELD = 'active';
    protected const IS_BLOG_FIELD = 'isBlog';
    protected const EXTERNAL_FIELD = 'external';
    protected const HIDE_FILTER_FIELD = 'hideFilter';
    protected const HIDE_TOP_FIELD = 'hideTop';
    protected const PRODUCT_BOX_LAYOUT_FIELD = 'productBoxLayout';
    protected const PRODUCT_STREAM_UUID_FIELD = 'productStreamUuid';
    protected const HIDE_SORTINGS_FIELD = 'hideSortings';
    protected const SORTING_UUIDS_FIELD = 'sortingUuids';
    protected const FACET_UUIDS_FIELD = 'facetUuids';
    protected const NAME_FIELD = 'name';
    protected const PATH_NAMES_FIELD = 'pathNames';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';
    protected const META_TITLE_FIELD = 'metaTitle';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';
    protected const CMS_HEADLINE_FIELD = 'cmsHeadline';
    protected const CMS_DESCRIPTION_FIELD = 'cmsDescription';

    public function __construct()
    {
        parent::__construct('category');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PATH_FIELD] = new LongTextField('path');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::LEVEL_FIELD] = new IntField('level');
        $this->fields[self::TEMPLATE_FIELD] = new StringField('template');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::IS_BLOG_FIELD] = new BoolField('is_blog');
        $this->fields[self::EXTERNAL_FIELD] = new StringField('external');
        $this->fields[self::HIDE_FILTER_FIELD] = new BoolField('hide_filter');
        $this->fields[self::HIDE_TOP_FIELD] = new BoolField('hide_top');
        $this->fields[self::PRODUCT_BOX_LAYOUT_FIELD] = new StringField('product_box_layout');
        $this->fields[self::PRODUCT_STREAM_UUID_FIELD] = new StringField('product_stream_uuid');
        $this->fields[self::HIDE_SORTINGS_FIELD] = new BoolField('hide_sortings');
        $this->fields[self::SORTING_UUIDS_FIELD] = new LongTextField('sorting_uuids');
        $this->fields[self::FACET_UUIDS_FIELD] = new LongTextField('facet_uuids');
        $this->fields['blogs'] = new SubresourceField(BlogWriteResource::class);
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', self::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', self::class, 'uuid'));
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', MediaWriteResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', MediaWriteResource::class, 'uuid'));
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', ShopWriteResource::class, 'uuid');
        $this->fields[self::PATH_NAMES_FIELD] = new TranslatedField('pathNames', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_KEYWORDS_FIELD] = new TranslatedField('metaKeywords', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', ShopWriteResource::class, 'uuid');
        $this->fields[self::META_DESCRIPTION_FIELD] = new TranslatedField('metaDescription', ShopWriteResource::class, 'uuid');
        $this->fields[self::CMS_HEADLINE_FIELD] = new TranslatedField('cmsHeadline', ShopWriteResource::class, 'uuid');
        $this->fields[self::CMS_DESCRIPTION_FIELD] = new TranslatedField('cmsDescription', ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(CategoryTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['parent'] = new SubresourceField(self::class);
        $this->fields['avoidCustomerGroups'] = new SubresourceField(CategoryAvoidCustomerGroupWriteResource::class);
        $this->fields['productCategories'] = new SubresourceField(ProductCategoryWriteResource::class);
        $this->fields['productCategorySeos'] = new SubresourceField(ProductCategorySeoWriteResource::class);
        $this->fields['shippingMethodCategories'] = new SubresourceField(ShippingMethodCategoryWriteResource::class);
        $this->fields['shops'] = new SubresourceField(ShopWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            BlogWriteResource::class,
            self::class,
            MediaWriteResource::class,
            CategoryTranslationWriteResource::class,
            CategoryAvoidCustomerGroupWriteResource::class,
            ProductCategoryWriteResource::class,
            ProductCategorySeoWriteResource::class,
            ShippingMethodCategoryWriteResource::class,
            ShopWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): CategoryWrittenEvent
    {
        $event = new CategoryWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[BlogWriteResource::class])) {
            $event->addEvent(BlogWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[MediaWriteResource::class])) {
            $event->addEvent(MediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CategoryTranslationWriteResource::class])) {
            $event->addEvent(CategoryTranslationWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[CategoryAvoidCustomerGroupWriteResource::class])) {
            $event->addEvent(CategoryAvoidCustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductCategoryWriteResource::class])) {
            $event->addEvent(ProductCategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductCategorySeoWriteResource::class])) {
            $event->addEvent(ProductCategorySeoWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShippingMethodCategoryWriteResource::class])) {
            $event->addEvent(ShippingMethodCategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ShopWriteResource::class])) {
            $event->addEvent(ShopWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

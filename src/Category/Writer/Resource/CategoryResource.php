<?php declare(strict_types=1);

namespace Shopware\Category\Writer\Resource;

use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CategoryResource extends Resource
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
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';
    protected const NAME_FIELD = 'name';
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
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
        $this->fields[self::UPDATED_AT_FIELD] = (new DateField('updated_at'))->setFlags(new Required());
        $this->fields['blogs'] = new SubresourceField(\Shopware\Framework\Write\Resource\BlogResource::class);
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', \Shopware\Category\Writer\Resource\CategoryResource::class);
        $this->fields['parentUuid'] = new FkField('parent_uuid', \Shopware\Category\Writer\Resource\CategoryResource::class, 'uuid');
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', \Shopware\Media\Writer\Resource\MediaResource::class);
        $this->fields['mediaUuid'] = new FkField('media_uuid', \Shopware\Media\Writer\Resource\MediaResource::class, 'uuid');
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::META_KEYWORDS_FIELD] = new TranslatedField('metaKeywords', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::META_TITLE_FIELD] = new TranslatedField('metaTitle', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::META_DESCRIPTION_FIELD] = new TranslatedField('metaDescription', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::CMS_HEADLINE_FIELD] = new TranslatedField('cmsHeadline', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::CMS_DESCRIPTION_FIELD] = new TranslatedField('cmsDescription', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Category\Writer\Resource\CategoryTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['parent'] = new SubresourceField(\Shopware\Category\Writer\Resource\CategoryResource::class);
        $this->fields['avoidCustomerGroups'] = new SubresourceField(\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::class);
        $this->fields['productCategories'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductCategoryResource::class);
        $this->fields['productCategorySeos'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductCategorySeoResource::class);
        $this->fields['shippingMethodCategories'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCategoryResource::class);
        $this->fields['shops'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\BlogResource::class,
            \Shopware\Category\Writer\Resource\CategoryResource::class,
            \Shopware\Media\Writer\Resource\MediaResource::class,
            \Shopware\Category\Writer\Resource\CategoryTranslationResource::class,
            \Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::class,
            \Shopware\Product\Writer\Resource\ProductCategoryResource::class,
            \Shopware\Product\Writer\Resource\ProductCategorySeoResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodCategoryResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Category\Event\CategoryWrittenEvent
    {
        $event = new \Shopware\Category\Event\CategoryWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\BlogResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\BlogResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryTranslationResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryTranslationResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryAvoidCustomerGroupResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductCategoryResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductCategoryResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductCategorySeoResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductCategorySeoResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCategoryResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodCategoryResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}

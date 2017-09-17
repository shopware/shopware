<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductMediaResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const IS_COVER_FIELD = 'isCover';
    protected const POSITION_FIELD = 'position';
    protected const MEDIA_UUID_FIELD = 'mediaUuid';
    protected const PARENT_UUID_FIELD = 'parentUuid';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('product_media');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::IS_COVER_FIELD] = (new IntField('is_cover'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::MEDIA_UUID_FIELD] = (new StringField('media_uuid'))->setFlags(new Required());
        $this->fields[self::PARENT_UUID_FIELD] = new StringField('parent_uuid');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class, 'uuid'));
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Product\Writer\Resource\ProductMediaTranslationResource::class, 'languageUuid'))->setFlags(new Required());
        $this->fields['mappings'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductMediaMappingResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class,
            \Shopware\Product\Writer\Resource\ProductMediaResource::class,
            \Shopware\Product\Writer\Resource\ProductMediaTranslationResource::class,
            \Shopware\Product\Writer\Resource\ProductMediaMappingResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Product\Event\ProductMediaWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductMediaWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductMediaResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductMediaResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductMediaTranslationResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductMediaTranslationResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductMediaMappingResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductMediaMappingResource::createWrittenEvent($updates));
        }

        return $event;
    }
}

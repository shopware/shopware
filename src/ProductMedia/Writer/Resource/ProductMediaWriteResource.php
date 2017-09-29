<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductMediaWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const IS_COVER_FIELD = 'isCover';
    protected const POSITION_FIELD = 'position';
    protected const PARENT_UUID_FIELD = 'parentUuid';

    public function __construct()
    {
        parent::__construct('product_media');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::IS_COVER_FIELD] = (new BoolField('is_cover'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::PARENT_UUID_FIELD] = new StringField('parent_uuid');
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class, 'uuid'));
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', \Shopware\Media\Writer\Resource\MediaWriteResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', \Shopware\Media\Writer\Resource\MediaWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['mappings'] = new SubresourceField(\Shopware\ProductMedia\Writer\Resource\ProductMediaMappingWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductWriteResource::class,
            \Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class,
            \Shopware\Media\Writer\Resource\MediaWriteResource::class,
            \Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class,
            \Shopware\ProductMedia\Writer\Resource\ProductMediaMappingWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductMedia\Event\ProductMediaWrittenEvent
    {
        $event = new \Shopware\ProductMedia\Event\ProductMediaWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::class])) {
            $event->addEvent(\Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Media\Writer\Resource\MediaWriteResource::class])) {
            $event->addEvent(\Shopware\Media\Writer\Resource\MediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class])) {
            $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductMedia\Writer\Resource\ProductMediaMappingWriteResource::class])) {
            $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaMappingWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

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
use Shopware\Media\Writer\Resource\MediaWriteResource;
use Shopware\Product\Writer\Resource\ProductWriteResource;
use Shopware\ProductDetail\Writer\Resource\ProductDetailWriteResource;
use Shopware\ProductMedia\Event\ProductMediaWrittenEvent;

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
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['productDetail'] = new ReferenceField('productDetailUuid', 'uuid', ProductDetailWriteResource::class);
        $this->fields['productDetailUuid'] = (new FkField('product_detail_uuid', ProductDetailWriteResource::class, 'uuid'));
        $this->fields['media'] = new ReferenceField('mediaUuid', 'uuid', MediaWriteResource::class);
        $this->fields['mediaUuid'] = (new FkField('media_uuid', MediaWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['mappings'] = new SubresourceField(ProductMediaMappingWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            ProductWriteResource::class,
            ProductDetailWriteResource::class,
            MediaWriteResource::class,
            self::class,
            ProductMediaMappingWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductMediaWrittenEvent
    {
        $event = new ProductMediaWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

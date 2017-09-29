<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductMediaMappingWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('product_media_mapping');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productMedia'] = new ReferenceField('productMediaUuid', 'uuid', \Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class);
        $this->fields['productMediaUuid'] = (new FkField('product_media_uuid', \Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class,
            \Shopware\ProductMedia\Writer\Resource\ProductMediaMappingWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductMedia\Event\ProductMediaMappingWrittenEvent
    {
        $event = new \Shopware\ProductMedia\Event\ProductMediaMappingWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::class])) {
            $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductMedia\Writer\Resource\ProductMediaMappingWriteResource::class])) {
            $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaMappingWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

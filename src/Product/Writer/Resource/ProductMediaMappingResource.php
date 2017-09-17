<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductMediaMappingResource extends Resource
{
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('product_media_mapping');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productMedia'] = new ReferenceField('productMediaUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductMediaResource::class);
        $this->fields['productMediaUuid'] = (new FkField('product_media_uuid', \Shopware\Product\Writer\Resource\ProductMediaResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductMediaResource::class,
            \Shopware\Product\Writer\Resource\ProductMediaMappingResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Product\Event\ProductMediaMappingWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductMediaMappingWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductMediaResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductMediaResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductMediaMappingResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductMediaMappingResource::createWrittenEvent($updates));
        }

        return $event;
    }
}

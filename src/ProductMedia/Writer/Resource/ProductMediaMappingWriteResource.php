<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\ProductMedia\Event\ProductMediaMappingWrittenEvent;

class ProductMediaMappingWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('product_media_mapping');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productMedia'] = new ReferenceField('productMediaUuid', 'uuid', ProductMediaWriteResource::class);
        $this->fields['productMediaUuid'] = (new FkField('product_media_uuid', ProductMediaWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductMediaWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductMediaMappingWrittenEvent
    {
        $event = new ProductMediaMappingWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ProductMediaWriteResource::class])) {
            $event->addEvent(ProductMediaWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

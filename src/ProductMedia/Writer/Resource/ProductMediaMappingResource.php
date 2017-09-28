<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
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
        $this->fields['productMedia'] = new ReferenceField('productMediaUuid', 'uuid', \Shopware\ProductMedia\Writer\Resource\ProductMediaResource::class);
        $this->fields['productMediaUuid'] = (new FkField('product_media_uuid', \Shopware\ProductMedia\Writer\Resource\ProductMediaResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductMedia\Writer\Resource\ProductMediaResource::class,
            \Shopware\ProductMedia\Writer\Resource\ProductMediaMappingResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\ProductMedia\Event\ProductMediaMappingWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\ProductMedia\Event\ProductMediaMappingWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\ProductMedia\Writer\Resource\ProductMediaMappingResource::createWrittenEvent($updates, $context));

        return $event;
    }
}

<?php declare(strict_types=1);

namespace Shopware\ProductStream\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductStreamTabWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('product_stream_tab');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productStream'] = new ReferenceField('productStreamUuid', 'uuid', \Shopware\ProductStream\Writer\Resource\ProductStreamWriteResource::class);
        $this->fields['productStreamUuid'] = (new FkField('product_stream_uuid', \Shopware\ProductStream\Writer\Resource\ProductStreamWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductStream\Writer\Resource\ProductStreamWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductWriteResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamTabWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductStream\Event\ProductStreamTabWrittenEvent
    {
        $event = new \Shopware\ProductStream\Event\ProductStreamTabWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamWriteResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamTabWriteResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamTabWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

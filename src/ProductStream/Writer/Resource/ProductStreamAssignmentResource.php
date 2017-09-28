<?php declare(strict_types=1);

namespace Shopware\ProductStream\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductStreamAssignmentResource extends Resource
{
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('product_stream_assignment');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields['productStream'] = new ReferenceField('productStreamUuid', 'uuid', \Shopware\ProductStream\Writer\Resource\ProductStreamResource::class);
        $this->fields['productStreamUuid'] = (new FkField('product_stream_uuid', \Shopware\ProductStream\Writer\Resource\ProductStreamResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ProductStream\Writer\Resource\ProductStreamResource::class,
            \Shopware\Product\Writer\Resource\ProductResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ?\Shopware\ProductStream\Event\ProductStreamAssignmentWrittenEvent
    {
        if (empty($updates) || !array_key_exists(self::class, $updates)) {
            return null;
        }

        $event = new \Shopware\ProductStream\Event\ProductStreamAssignmentWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\Product\Writer\Resource\ProductResource::createWrittenEvent($updates, $context));
        $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::createWrittenEvent($updates, $context));

        return $event;
    }
}

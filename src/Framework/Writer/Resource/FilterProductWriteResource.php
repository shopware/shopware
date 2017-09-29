<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class FilterProductWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('filter_product');

        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', \Shopware\Product\Writer\Resource\ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['filterValue'] = new ReferenceField('filterValueUuid', 'uuid', \Shopware\Framework\Write\Resource\FilterValueWriteResource::class);
        $this->fields['filterValueUuid'] = (new FkField('filter_value_uuid', \Shopware\Framework\Write\Resource\FilterValueWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterValueWriteResource::class,
            \Shopware\Framework\Write\Resource\FilterProductWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\FilterProductWrittenEvent
    {
        $event = new \Shopware\Framework\Event\FilterProductWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterValueWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterValueWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\FilterProductWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\FilterProductWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

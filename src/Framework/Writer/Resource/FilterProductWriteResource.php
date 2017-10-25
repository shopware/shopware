<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\FilterProductWrittenEvent;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Writer\Resource\ProductWriteResource;

class FilterProductWriteResource extends WriteResource
{
    public function __construct()
    {
        parent::__construct('filter_product');

        $this->fields['product'] = new ReferenceField('productUuid', 'uuid', ProductWriteResource::class);
        $this->fields['productUuid'] = (new FkField('product_uuid', ProductWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['filterValue'] = new ReferenceField('filterValueUuid', 'uuid', FilterValueWriteResource::class);
        $this->fields['filterValueUuid'] = (new FkField('filter_value_uuid', FilterValueWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductWriteResource::class,
            FilterValueWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): FilterProductWrittenEvent
    {
        $event = new FilterProductWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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

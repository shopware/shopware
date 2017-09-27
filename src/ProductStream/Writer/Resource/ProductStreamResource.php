<?php declare(strict_types=1);

namespace Shopware\ProductStream\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductStreamResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const CONDITIONS_FIELD = 'conditions';
    protected const TYPE_FIELD = 'type';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('product_stream');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::CONDITIONS_FIELD] = new LongTextField('conditions');
        $this->fields[self::TYPE_FIELD] = new IntField('type');
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields['listingSorting'] = new ReferenceField('listingSortingUuid', 'uuid', \Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class);
        $this->fields['listingSortingUuid'] = (new FkField('listing_sorting_uuid', \Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class, 'uuid'));
        $this->fields['assignments'] = new SubresourceField(\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::class);
        $this->fields['tabs'] = new SubresourceField(\Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::class,
            \Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\ProductStream\Event\ProductStreamWrittenEvent
    {
        $event = new \Shopware\ProductStream\Event\ProductStreamWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\ListingSorting\Writer\Resource\ListingSortingResource::class])) {
            $event->addEvent(\Shopware\ListingSorting\Writer\Resource\ListingSortingResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamAssignmentResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::class])) {
            $event->addEvent(\Shopware\ProductStream\Writer\Resource\ProductStreamTabResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

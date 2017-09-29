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
use Shopware\Framework\Write\WriteResource;
use Shopware\ListingSorting\Writer\Resource\ListingSortingWriteResource;
use Shopware\ProductStream\Event\ProductStreamWrittenEvent;

class ProductStreamWriteResource extends WriteResource
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
        $this->fields['listingSorting'] = new ReferenceField('listingSortingUuid', 'uuid', ListingSortingWriteResource::class);
        $this->fields['listingSortingUuid'] = (new FkField('listing_sorting_uuid', ListingSortingWriteResource::class, 'uuid'));
        $this->fields['assignments'] = new SubresourceField(ProductStreamAssignmentWriteResource::class);
        $this->fields['tabs'] = new SubresourceField(ProductStreamTabWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            ListingSortingWriteResource::class,
            self::class,
            ProductStreamAssignmentWriteResource::class,
            ProductStreamTabWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ProductStreamWrittenEvent
    {
        $event = new ProductStreamWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[ListingSortingWriteResource::class])) {
            $event->addEvent(ListingSortingWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductStreamAssignmentWriteResource::class])) {
            $event->addEvent(ProductStreamAssignmentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[ProductStreamTabWriteResource::class])) {
            $event->addEvent(ProductStreamTabWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

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

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductStreamWrittenEvent
    {
        $event = new ProductStreamWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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

<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Event\ProductEsdSerialWrittenEvent;

class ProductEsdSerialWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const SERIAL_NUMBER_FIELD = 'serialNumber';

    public function __construct()
    {
        parent::__construct('product_esd_serial');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SERIAL_NUMBER_FIELD] = (new StringField('serial_number'))->setFlags(new Required());
        $this->fields['productEsd'] = new ReferenceField('productEsdUuid', 'uuid', ProductEsdWriteResource::class);
        $this->fields['productEsdUuid'] = (new FkField('product_esd_uuid', ProductEsdWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ProductEsdWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductEsdSerialWrittenEvent
    {
        $event = new ProductEsdSerialWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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

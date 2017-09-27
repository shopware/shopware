<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductEsdSerialResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const SERIAL_NUMBER_FIELD = 'serialNumber';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('product_esd_serial');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::SERIAL_NUMBER_FIELD] = (new StringField('serial_number'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['productEsd'] = new ReferenceField('productEsdUuid', 'uuid', \Shopware\Product\Writer\Resource\ProductEsdResource::class);
        $this->fields['productEsdUuid'] = (new FkField('product_esd_uuid', \Shopware\Product\Writer\Resource\ProductEsdResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductEsdResource::class,
            \Shopware\Product\Writer\Resource\ProductEsdSerialResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductEsdSerialWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductEsdSerialWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductEsdResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductEsdResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductEsdSerialResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductEsdSerialResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }

    public function getDefaults(string $type): array
    {
        if (self::FOR_UPDATE === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if (self::FOR_INSERT === $type) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}

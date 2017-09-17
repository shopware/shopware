<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShopPageGroupResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const KEY_FIELD = 'key';
    protected const ACTIVE_FIELD = 'active';
    protected const MAPPING_ID_FIELD = 'mappingId';

    public function __construct()
    {
        parent::__construct('shop_page_group');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::KEY_FIELD] = (new StringField('key'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::MAPPING_ID_FIELD] = new IntField('mapping_id');
        $this->fields['mappings'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopPageGroupResource::class,
            \Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Shop\Event\ShopPageGroupWrittenEvent
    {
        $event = new \Shopware\Shop\Event\ShopPageGroupWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopPageGroupResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopPageGroupResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::createWrittenEvent($updates));
        }

        return $event;
    }
}

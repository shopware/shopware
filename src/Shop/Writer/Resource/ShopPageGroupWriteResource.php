<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Event\ShopPageGroupWrittenEvent;

class ShopPageGroupWriteResource extends WriteResource
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
        $this->fields['mappings'] = new SubresourceField(ShopPageGroupMappingWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            ShopPageGroupMappingWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopPageGroupWrittenEvent
    {
        $event = new ShopPageGroupWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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

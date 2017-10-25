<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Shop\Event\ShopPageGroupMappingWrittenEvent;

class ShopPageGroupMappingWriteResource extends WriteResource
{
    protected const SHOP_ID_FIELD = 'shopId';
    protected const SHOP_PAGE_GROUP_ID_FIELD = 'shopPageGroupId';

    public function __construct()
    {
        parent::__construct('shop_page_group_mapping');

        $this->primaryKeyFields[self::SHOP_ID_FIELD] = (new IntField('shop_id'))->setFlags(new Required());
        $this->primaryKeyFields[self::SHOP_PAGE_GROUP_ID_FIELD] = (new IntField('shop_page_group_id'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shopPageGroup'] = new ReferenceField('shopPageGroupUuid', 'uuid', ShopPageGroupWriteResource::class);
        $this->fields['shopPageGroupUuid'] = (new FkField('shop_page_group_uuid', ShopPageGroupWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            ShopWriteResource::class,
            ShopPageGroupWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopPageGroupMappingWrittenEvent
    {
        $event = new ShopPageGroupMappingWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

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

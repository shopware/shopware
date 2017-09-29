<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ShopPageGroupMappingWriteResource extends WriteResource
{
    protected const SHOP_ID_FIELD = 'shopId';
    protected const SHOP_PAGE_GROUP_ID_FIELD = 'shopPageGroupId';

    public function __construct()
    {
        parent::__construct('shop_page_group_mapping');

        $this->primaryKeyFields[self::SHOP_ID_FIELD] = (new IntField('shop_id'))->setFlags(new Required());
        $this->primaryKeyFields[self::SHOP_PAGE_GROUP_ID_FIELD] = (new IntField('shop_page_group_id'))->setFlags(new Required());
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shopPageGroup'] = new ReferenceField('shopPageGroupUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopPageGroupWriteResource::class);
        $this->fields['shopPageGroupUuid'] = (new FkField('shop_page_group_uuid', \Shopware\Shop\Writer\Resource\ShopPageGroupWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopPageGroupWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopPageGroupMappingWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Shop\Event\ShopPageGroupMappingWrittenEvent
    {
        $event = new \Shopware\Shop\Event\ShopPageGroupMappingWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopPageGroupWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopPageGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopPageGroupMappingWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopPageGroupMappingWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

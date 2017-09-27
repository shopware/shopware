<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShopPageGroupMappingResource extends Resource
{
    protected const SHOP_ID_FIELD = 'shopId';
    protected const SHOP_PAGE_GROUP_ID_FIELD = 'shopPageGroupId';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const UPDATED_AT_FIELD = 'updatedAt';

    public function __construct()
    {
        parent::__construct('shop_page_group_mapping');

        $this->primaryKeyFields[self::SHOP_ID_FIELD] = (new IntField('shop_id'))->setFlags(new Required());
        $this->primaryKeyFields[self::SHOP_PAGE_GROUP_ID_FIELD] = (new IntField('shop_page_group_id'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = new DateField('created_at');
        $this->fields[self::UPDATED_AT_FIELD] = new DateField('updated_at');
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shopPageGroup'] = new ReferenceField('shopPageGroupUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopPageGroupResource::class);
        $this->fields['shopPageGroupUuid'] = (new FkField('shop_page_group_uuid', \Shopware\Shop\Writer\Resource\ShopPageGroupResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\Shop\Writer\Resource\ShopPageGroupResource::class,
            \Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Shop\Event\ShopPageGroupMappingWrittenEvent
    {
        $event = new \Shopware\Shop\Event\ShopPageGroupMappingWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopPageGroupResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopPageGroupResource::createWrittenEvent($updates, $context));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::createWrittenEvent($updates, $context));
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

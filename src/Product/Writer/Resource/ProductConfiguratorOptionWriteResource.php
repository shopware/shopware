<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductConfiguratorOptionWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const GROUP_ID_FIELD = 'groupId';
    protected const PRODUCT_CONFIGURATOR_GROUP_UUID_FIELD = 'productConfiguratorGroupUuid';
    protected const NAME_FIELD = 'name';
    protected const POSITION_FIELD = 'position';

    public function __construct()
    {
        parent::__construct('product_configurator_option');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::GROUP_ID_FIELD] = new IntField('group_id');
        $this->fields[self::PRODUCT_CONFIGURATOR_GROUP_UUID_FIELD] = (new StringField('product_configurator_group_uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = new TranslatedField('name', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid');
        $this->fields['translations'] = (new SubresourceField(\Shopware\Product\Writer\Resource\ProductConfiguratorOptionTranslationWriteResource::class, 'languageUuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorOptionWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductConfiguratorOptionTranslationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorOptionWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorOptionWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorOptionWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorOptionWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorOptionTranslationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorOptionTranslationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ProductConfiguratorSetGroupRelationWriteResource extends WriteResource
{
    protected const SET_ID_FIELD = 'setId';
    protected const UUID_FIELD = 'uuid';
    protected const GROUP_ID_FIELD = 'groupId';

    public function __construct()
    {
        parent::__construct('product_configurator_set_group_relation');

        $this->primaryKeyFields[self::SET_ID_FIELD] = new IntField('set_id');
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->primaryKeyFields[self::GROUP_ID_FIELD] = new IntField('group_id');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorSetGroupRelationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorSetGroupRelationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorSetGroupRelationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorSetGroupRelationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorSetGroupRelationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

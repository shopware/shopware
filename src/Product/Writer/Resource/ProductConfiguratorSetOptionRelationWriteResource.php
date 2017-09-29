<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\WriteResource;

class ProductConfiguratorSetOptionRelationWriteResource extends WriteResource
{
    protected const SET_ID_FIELD = 'setId';
    protected const OPTION_ID_FIELD = 'optionId';

    public function __construct()
    {
        parent::__construct('product_configurator_set_option_relation');

        $this->primaryKeyFields[self::SET_ID_FIELD] = new IntField('set_id');
        $this->primaryKeyFields[self::OPTION_ID_FIELD] = new IntField('option_id');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Product\Writer\Resource\ProductConfiguratorSetOptionRelationWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Product\Event\ProductConfiguratorSetOptionRelationWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorSetOptionRelationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorSetOptionRelationWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorSetOptionRelationWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

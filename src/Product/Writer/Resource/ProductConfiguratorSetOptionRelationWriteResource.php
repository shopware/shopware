<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Event\ProductConfiguratorSetOptionRelationWrittenEvent;

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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): ProductConfiguratorSetOptionRelationWrittenEvent
    {
        $event = new ProductConfiguratorSetOptionRelationWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[self::class])) {
            $event->addEvent(self::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

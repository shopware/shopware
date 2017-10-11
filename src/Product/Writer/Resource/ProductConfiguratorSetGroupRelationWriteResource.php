<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;
use Shopware\Product\Event\ProductConfiguratorSetGroupRelationWrittenEvent;

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
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductConfiguratorSetGroupRelationWrittenEvent
    {
        $event = new ProductConfiguratorSetGroupRelationWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

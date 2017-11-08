<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Event\ProductConfiguratorOptionRelationWrittenEvent;

class ProductConfiguratorOptionRelationWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const PRODUCT_ID_FIELD = 'productId';
    protected const OPTION_ID_FIELD = 'optionId';

    public function __construct()
    {
        parent::__construct('product_configurator_option_relation');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::PRODUCT_ID_FIELD] = (new IntField('product_id'))->setFlags(new Required());
        $this->fields[self::OPTION_ID_FIELD] = (new IntField('option_id'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductConfiguratorOptionRelationWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ProductConfiguratorOptionRelationWrittenEvent($uuids, $context, $rawData, $errors);

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

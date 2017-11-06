<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Api\Write\Field\FloatField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Event\ProductConfiguratorTemplatePriceWrittenEvent;

class ProductConfiguratorTemplatePriceWriteResource extends WriteResource
{
    protected const TEMPLATE_ID_FIELD = 'templateId';
    protected const CUSTOMER_GROUP_KEY_FIELD = 'customerGroupKey';
    protected const FROM_FIELD = 'from';
    protected const TO_FIELD = 'to';
    protected const PRICE_FIELD = 'price';
    protected const PSEUDOPRICE_FIELD = 'pseudoprice';
    protected const PERCENT_FIELD = 'percent';
    protected const UUID_FIELD = 'uuid';

    public function __construct()
    {
        parent::__construct('product_configurator_template_price');

        $this->fields[self::TEMPLATE_ID_FIELD] = new IntField('template_id');
        $this->fields[self::CUSTOMER_GROUP_KEY_FIELD] = (new StringField('customer_group_key'))->setFlags(new Required());
        $this->fields[self::FROM_FIELD] = (new IntField('from'))->setFlags(new Required());
        $this->fields[self::TO_FIELD] = (new StringField('to'))->setFlags(new Required());
        $this->fields[self::PRICE_FIELD] = new FloatField('price');
        $this->fields[self::PSEUDOPRICE_FIELD] = new FloatField('pseudoprice');
        $this->fields[self::PERCENT_FIELD] = new FloatField('percent');
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ProductConfiguratorTemplatePriceWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ProductConfiguratorTemplatePriceWrittenEvent($uuids, $context, $rawData, $errors);

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

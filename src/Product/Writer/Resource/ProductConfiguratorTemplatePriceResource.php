<?php declare(strict_types=1);

namespace Shopware\Product\Writer\Resource;

use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ProductConfiguratorTemplatePriceResource extends Resource
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
            \Shopware\Product\Writer\Resource\ProductConfiguratorTemplatePriceResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Product\Event\ProductConfiguratorTemplatePriceWrittenEvent
    {
        $event = new \Shopware\Product\Event\ProductConfiguratorTemplatePriceWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductConfiguratorTemplatePriceResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductConfiguratorTemplatePriceResource::createWrittenEvent($updates));
        }

        return $event;
    }
}

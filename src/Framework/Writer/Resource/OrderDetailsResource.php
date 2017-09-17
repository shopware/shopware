<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class OrderDetailsResource extends Resource
{
    protected const ORDERID_FIELD = 'orderID';
    protected const ORDERNUMBER_FIELD = 'ordernumber';
    protected const ARTICLEID_FIELD = 'articleID';
    protected const ARTICLEORDERNUMBER_FIELD = 'articleordernumber';
    protected const PRICE_FIELD = 'price';
    protected const QUANTITY_FIELD = 'quantity';
    protected const NAME_FIELD = 'name';
    protected const STATUS_FIELD = 'status';
    protected const SHIPPED_FIELD = 'shipped';
    protected const SHIPPEDGROUP_FIELD = 'shippedgroup';
    protected const RELEASEDATE_FIELD = 'releasedate';
    protected const MODUS_FIELD = 'modus';
    protected const ESDARTICLE_FIELD = 'esdarticle';
    protected const TAXID_FIELD = 'taxID';
    protected const TAX_RATE_FIELD = 'taxRate';
    protected const CONFIG_FIELD = 'config';
    protected const EAN_FIELD = 'ean';
    protected const UNIT_FIELD = 'unit';
    protected const PACK_UNIT_FIELD = 'packUnit';

    public function __construct()
    {
        parent::__construct('s_order_details');

        $this->fields[self::ORDERID_FIELD] = new IntField('orderID');
        $this->fields[self::ORDERNUMBER_FIELD] = (new StringField('ordernumber'))->setFlags(new Required());
        $this->fields[self::ARTICLEID_FIELD] = new IntField('articleID');
        $this->fields[self::ARTICLEORDERNUMBER_FIELD] = (new StringField('articleordernumber'))->setFlags(new Required());
        $this->fields[self::PRICE_FIELD] = new FloatField('price');
        $this->fields[self::QUANTITY_FIELD] = new IntField('quantity');
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::STATUS_FIELD] = new IntField('status');
        $this->fields[self::SHIPPED_FIELD] = new IntField('shipped');
        $this->fields[self::SHIPPEDGROUP_FIELD] = new IntField('shippedgroup');
        $this->fields[self::RELEASEDATE_FIELD] = new DateField('releasedate');
        $this->fields[self::MODUS_FIELD] = (new IntField('modus'))->setFlags(new Required());
        $this->fields[self::ESDARTICLE_FIELD] = (new IntField('esdarticle'))->setFlags(new Required());
        $this->fields[self::TAXID_FIELD] = new IntField('taxID');
        $this->fields[self::TAX_RATE_FIELD] = (new FloatField('tax_rate'))->setFlags(new Required());
        $this->fields[self::CONFIG_FIELD] = (new LongTextField('config'))->setFlags(new Required());
        $this->fields[self::EAN_FIELD] = new StringField('ean');
        $this->fields[self::UNIT_FIELD] = new StringField('unit');
        $this->fields[self::PACK_UNIT_FIELD] = new StringField('pack_unit');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\OrderDetailsResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\OrderDetailsWrittenEvent
    {
        $event = new \Shopware\Framework\Event\OrderDetailsWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\OrderDetailsResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\OrderDetailsResource::createWrittenEvent($updates));
        }

        return $event;
    }
}

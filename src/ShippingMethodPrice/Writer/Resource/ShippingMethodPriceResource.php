<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Writer\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class ShippingMethodPriceResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const FROM_FIELD = 'from';
    protected const VALUE_FIELD = 'value';
    protected const FACTOR_FIELD = 'factor';
    protected const SHIPPING_METHOD_ID_FIELD = 'shippingMethodId';

    public function __construct()
    {
        parent::__construct('shipping_method_price');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::FROM_FIELD] = (new FloatField('from'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = (new FloatField('value'))->setFlags(new Required());
        $this->fields[self::FACTOR_FIELD] = (new FloatField('factor'))->setFlags(new Required());
        $this->fields[self::SHIPPING_METHOD_ID_FIELD] = (new IntField('shipping_method_id'))->setFlags(new Required());
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class,
            \Shopware\ShippingMethodPrice\Writer\Resource\ShippingMethodPriceResource::class
        ];
    }
}

<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Gateway\Resource;

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

class ShippingMethodPaymentMethodResource extends Resource
{
    protected const SHIPPING_METHOD_ID_FIELD = 'shippingMethodId';
    protected const PAYMENT_METHOD_ID_FIELD = 'paymentMethodId';

    public function __construct()
    {
        parent::__construct('shipping_method_payment_method');
        
        $this->primaryKeyFields[self::SHIPPING_METHOD_ID_FIELD] = (new IntField('shipping_method_id'))->setFlags(new Required());
        $this->primaryKeyFields[self::PAYMENT_METHOD_ID_FIELD] = (new IntField('payment_method_id'))->setFlags(new Required());
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', \Shopware\PaymentMethod\Gateway\Resource\PaymentMethodResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', \Shopware\PaymentMethod\Gateway\Resource\PaymentMethodResource::class, 'uuid'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodResource::class,
            \Shopware\PaymentMethod\Gateway\Resource\PaymentMethodResource::class,
            \Shopware\ShippingMethod\Gateway\Resource\ShippingMethodPaymentMethodResource::class
        ];
    }
}
